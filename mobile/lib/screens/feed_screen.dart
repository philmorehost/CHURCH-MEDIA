import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';
import 'package:visibility_detector/visibility_detector.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../models/models.dart';
import '../services/api_client.dart';
import '../services/share_service.dart';
import '../theme/app_theme.dart';
import '../widgets/common.dart';

/// Vertical, full-screen, infinite-scrolling feed — behaves like Instagram
/// Reels: swipe up for the next post, tap a video to mute/unmute, double-tap
/// to like, and use the right-hand rail for like/comment/share/save.
/// Mirrors views/feed.php + assets/js/feed.js.
class FeedScreen extends StatefulWidget {
  const FeedScreen({super.key});
  @override
  State<FeedScreen> createState() => FeedScreenState();
}

class FeedScreenState extends State<FeedScreen> {
  final _api = ApiClient();
  final _pageController = PageController();
  final List<Post> _posts = [];
  List<Category> _categories = [];
  String? _activeCategory;
  bool _savedOnly = false;
  int _page = 1;
  bool _hasMore = true;
  bool _loading = false;
  final Set<int> _viewedIds = {};

  @override
  void initState() {
    super.initState();
    _api.fetchCategories().then((c) => setState(() => _categories = c));
    _loadMore();
  }

  /// Re-runs the initial load (categories + first feed page). Called by the
  /// shell when the Feed tab is (re)tapped, and by pull-to-refresh/retry, so a
  /// silently failed first load recovers without needing an app restart.
  Future<void> refresh() async {
    if (_loading) return;
    _api.fetchCategories().then((c) => setState(() => _categories = c));
    await _loadMore();
  }

  Future<void> _loadMore() async {
    if (_loading || !_hasMore) return;
    setState(() => _loading = true);
    try {
      final result = await _api.fetchFeed(page: _page, category: _activeCategory, saved: _savedOnly);
      setState(() {
        _posts.addAll(result.posts);
        _hasMore = result.hasMore;
        _page++;
      });
    } catch (_) {
      // Network hiccup — silently allow a retry on the next scroll.
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _selectCategory(String? slug) {
    setState(() {
      _activeCategory = slug;
      _posts.clear();
      _page = 1;
      _hasMore = true;
      _viewedIds.clear();
    });
    _pageController.jumpToPage(0);
    _loadMore();
  }

  void _setSavedOnly(bool saved) {
    setState(() {
      _savedOnly = saved;
      _posts.clear();
      _page = 1;
      _hasMore = true;
      _viewedIds.clear();
    });
    _pageController.jumpToPage(0);
    _loadMore();
  }

  void _onPageChanged(int index) {
    if (index >= _posts.length - 2) _loadMore();
    if (index < _posts.length) {
      final post = _posts[index];
      if (_viewedIds.add(post.id)) _api.pingView(post.id);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            _buildTopBar(),
            if (_categories.isNotEmpty) _buildChips(),
            Expanded(
              child: _posts.isEmpty
                  ? (_loading
                      ? const LoadingView()
                      : RefreshIndicator(
                          onRefresh: refresh,
                          color: AppColors.gold,
                          child: ListView(
                            physics: const AlwaysScrollableScrollPhysics(),
                            children: [
                              const SizedBox(height: 80),
                              const EmptyState(message: 'No reels in the feed yet.'),
                              const SizedBox(height: 16),
                              Center(
                                child: OutlinedButton.icon(
                                  onPressed: refresh,
                                  icon: const Icon(Icons.refresh),
                                  label: const Text('Retry'),
                                ),
                              ),
                            ],
                          ),
                        ))
                  : PageView.builder(
                      controller: _pageController,
                      scrollDirection: Axis.vertical,
                      itemCount: _posts.length,
                      onPageChanged: _onPageChanged,
                      itemBuilder: (context, index) => _FeedSlide(
                        post: _posts[index],
                        api: _api,
                        onLikeChanged: (liked, count) => setState(() {
                          _posts[index].likedByViewer = liked;
                        }),
                        onCommentAdded: (count) => setState(() {
                          _posts[index].commentsCount = count;
                        }),
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTopBar() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: const BoxDecoration(color: Colors.black, border: Border(bottom: BorderSide(color: Color(0xFF1F1F1F)))),
      child: Row(
        children: [
          const Text('Reels', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: Colors.white)),
          const Spacer(),
          _toggle('For You', !_savedOnly, () => _setSavedOnly(false)),
          const SizedBox(width: 16),
          _toggle('Saved', _savedOnly, () => _setSavedOnly(true)),
        ],
      ),
    );
  }

  Widget _toggle(String label, bool active, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
        decoration: BoxDecoration(
          color: active ? Colors.white : Colors.transparent,
          borderRadius: BorderRadius.circular(999),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: active ? Colors.black : AppColors.inkDim,
            fontSize: 12.5,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    );
  }

  Widget _buildChips() {
    return SizedBox(
      height: 46,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        children: [
          _chip('All', null),
          for (final c in _categories) _chip(c.name, c.slug),
        ],
      ),
    );
  }

  Widget _chip(String label, String? slug) {
    final active = _activeCategory == slug;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: ChoiceChip(
        label: Text(label),
        selected: active,
        onSelected: (_) => _selectCategory(slug),
        backgroundColor: Colors.white.withValues(alpha: 0.08),
        selectedColor: AppColors.gold.withValues(alpha: 0.22),
        labelStyle: TextStyle(color: active ? AppColors.goldSoft : AppColors.inkDim, fontSize: 12.5),
        side: BorderSide(color: active ? AppColors.gold.withValues(alpha: 0.4) : Colors.transparent),
      ),
    );
  }
}

class _FeedSlide extends StatefulWidget {
  final Post post;
  final ApiClient api;
  final void Function(bool liked, int count) onLikeChanged;
  final void Function(int count) onCommentAdded;
  const _FeedSlide({required this.post, required this.api, required this.onLikeChanged, required this.onCommentAdded});

  @override
  State<_FeedSlide> createState() => _FeedSlideState();
}

class _FeedSlideState extends State<_FeedSlide> {
  VideoPlayerController? _videoController;
  int _mediaIndex = 0;
  bool _muted = true;
  bool _liking = false;
  bool _saving = false;
  bool _burst = false;

  MediaItem? get _activeMedia => widget.post.mediaItems.isEmpty ? null : widget.post.mediaItems[_mediaIndex];

  @override
  void dispose() {
    _videoController?.dispose();
    super.dispose();
  }

  void _setupVideoIfNeeded() {
    final media = _activeMedia;
    if (media == null || media.type != 'video' || media.source == 'youtube' || media.fileUrl == null) return;
    if (_videoController != null) return;
    final controller = VideoPlayerController.networkUrl(Uri.parse(media.fileUrl!));
    _videoController = controller;
    controller.setLooping(true);
    controller.setVolume(_muted ? 0 : 1);
    controller.initialize().then((_) {
      if (mounted) setState(() {});
    });
  }

  void _onVisibilityChanged(VisibilityInfo info) {
    final controller = _videoController;
    if (controller == null) return;
    if (info.visibleFraction > 0.6) {
      controller.play();
    } else {
      controller.pause();
    }
  }

  void _toggleMute() {
    setState(() => _muted = !_muted);
    _videoController?.setVolume(_muted ? 0 : 1);
  }

  Future<void> _toggleLike({bool doubleTap = false}) async {
    if (_liking) return;
    if (doubleTap && widget.post.likedByViewer) return;
    setState(() => _liking = true);
    try {
      final result = await widget.api.toggleLike(widget.post.id);
      widget.onLikeChanged(result.liked, result.likesCount);
      setState(() {
        widget.post.likedByViewer = result.liked;
      });
      if (result.liked) _fireBurst();
    } catch (_) {
    } finally {
      if (mounted) setState(() => _liking = false);
    }
  }

  void _fireBurst() {
    setState(() => _burst = true);
    Future.delayed(const Duration(milliseconds: 480), () {
      if (mounted) setState(() => _burst = false);
    });
  }

  Future<void> _toggleSave() async {
    if (_saving) return;
    setState(() => _saving = true);
    try {
      final result = await widget.api.toggleSave(widget.post.id);
      setState(() {
        widget.post.savedByViewer = result.saved;
        widget.post.savesCount = result.savesCount;
      });
    } catch (_) {
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _openComments() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _CommentSheet(
        postId: widget.post.id,
        api: widget.api,
        onAdded: (count) => widget.onCommentAdded(count),
      ),
    );
  }

  String _formatCount(int n) {
    if (n >= 1000000) return '${(n / 1000000).toStringAsFixed(1)}M';
    if (n >= 1000) return '${(n / 1000).toStringAsFixed(1)}K';
    return '$n';
  }

  @override
  Widget build(BuildContext context) {
    _setupVideoIfNeeded();
    final post = widget.post;

    return VisibilityDetector(
      key: Key('feed-slide-${post.id}'),
      onVisibilityChanged: _onVisibilityChanged,
      child: Stack(
        fit: StackFit.expand,
        children: [
          _buildMedia(),
          const DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [Color(0x66000000), Colors.transparent, Colors.transparent, Color(0xDD000000)],
                stops: [0, 0.25, 0.55, 1],
              ),
            ),
          ),
          Positioned(
            top: 12,
            left: 16,
            child: _badge(post.postType == 'vertical_reel' ? 'Reel' : (post.postType == 'carousel' ? 'Carousel' : 'Photo')),
          ),
          Positioned(
            left: 16,
            right: 96,
            bottom: 20,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    _avatar(post.authorName),
                    const SizedBox(width: 8),
                    Flexible(
                      child: Text(
                        '@${post.authorUsername.isNotEmpty ? post.authorUsername : post.authorName.toLowerCase().replaceAll(' ', '.')}',
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13.5),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(width: 4),
                    _verified(),
                  ],
                ),
                const SizedBox(height: 8),
                if (post.caption != null && post.caption!.isNotEmpty)
                  Text(post.caption!, style: const TextStyle(color: Colors.white, fontSize: 14), maxLines: 3, overflow: TextOverflow.ellipsis),
                const SizedBox(height: 10),
                const Row(
                  children: [
                    Icon(Icons.music_note, color: AppColors.goldSoft, size: 15),
                    SizedBox(width: 5),
                    Text('Original audio', style: TextStyle(color: Colors.white, fontSize: 12)),
                  ],
                ),
              ],
            ),
          ),
          Positioned(
            right: 12,
            bottom: 90,
            child: Column(
              children: [
                _actionButton(
                  icon: post.likedByViewer ? Icons.favorite : Icons.favorite_border,
                  color: post.likedByViewer ? const Color(0xFFFF4D6D) : Colors.white,
                  label: _formatCount(post.likesCount),
                  onTap: () => _toggleLike(),
                ),
                const SizedBox(height: 18),
                _actionButton(
                  icon: Icons.mode_comment_outlined,
                  label: _formatCount(post.commentsCount),
                  onTap: _openComments,
                ),
                const SizedBox(height: 18),
                _actionButton(
                  icon: Icons.ios_share,
                  label: '',
                  onTap: () => ShareService.share(
                    text: post.caption ?? 'Check this out',
                    uri: '${ApiClient.baseUrl}/feed',
                  ),
                  iconOnly: true,
                ),
                const SizedBox(height: 18),
                _actionButton(
                  icon: post.savedByViewer ? Icons.bookmark : Icons.bookmark_border,
                  color: post.savedByViewer ? AppColors.goldSoft : Colors.white,
                  label: '',
                  onTap: _toggleSave,
                  iconOnly: true,
                ),
              ],
            ),
          ),
          if (widget.post.mediaItems.length > 1) ...[
            Positioned.fill(
              child: Row(children: [
                Expanded(child: GestureDetector(onTap: _prevMedia, behavior: HitTestBehavior.translucent)),
                const Expanded(flex: 2, child: SizedBox()),
                Expanded(child: GestureDetector(onTap: _nextMedia, behavior: HitTestBehavior.translucent)),
              ]),
            ),
          ],
          AnimatedOpacity(
            opacity: _burst ? 1 : 0,
            duration: const Duration(milliseconds: 200),
            child: IgnorePointer(
              child: Center(
                child: AnimatedScale(
                  scale: _burst ? 1 : 0.3,
                  duration: const Duration(milliseconds: 300),
                  curve: Curves.easeOutBack,
                  child: const Icon(Icons.favorite, size: 96, color: Color(0xFFFF4D6D)),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _avatar(String name) {
    return Container(
      width: 34,
      height: 34,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: const LinearGradient(colors: [Color(0xFFF09433), Color(0xFFE6683C), Color(0xFFDC2743)]),
        border: Border.all(color: Colors.white, width: 2),
      ),
      alignment: Alignment.center,
      child: Text(
        _initial(name),
        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13),
      ),
    );
  }

  Widget _verified() {
    return Container(
      width: 15,
      height: 15,
      decoration: const BoxDecoration(color: Color(0xFF3897F0), shape: BoxShape.circle),
      alignment: Alignment.center,
      child: const Icon(Icons.check, size: 10, color: Colors.white),
    );
  }

  void _prevMedia() => setState(() {
        _videoController?.dispose();
        _videoController = null;
        _mediaIndex = (_mediaIndex - 1 + widget.post.mediaItems.length) % widget.post.mediaItems.length;
      });

  void _nextMedia() => setState(() {
        _videoController?.dispose();
        _videoController = null;
        _mediaIndex = (_mediaIndex + 1) % widget.post.mediaItems.length;
      });

  Widget _buildMedia() {
    final media = _activeMedia;
    if (media == null) return Container(color: AppColors.bg2);

    if (media.type == 'video' && media.source == 'youtube') {
      final id = RegExp(r'/embed/([a-zA-Z0-9_-]+)').firstMatch(media.fileUrl ?? '')?.group(1) ?? media.fileUrl ?? '';
      return GestureDetector(
        onTap: _toggleMute,
        onDoubleTap: () => _toggleLike(doubleTap: true),
        child: Container(
          color: Colors.black,
          child: id.isNotEmpty
              ? _YoutubePlayer(key: ValueKey('yt-$id-${_muted ? 1 : 0}'), videoId: id, muted: _muted)
              : const LoadingView(),
        ),
      );
    }

    if (media.type == 'video') {
      final controller = _videoController;
      return GestureDetector(
        onTap: _toggleMute,
        onDoubleTap: () => _toggleLike(doubleTap: true),
        child: Container(
          color: Colors.black,
          child: controller != null && controller.value.isInitialized
              ? FittedBox(
                  fit: BoxFit.cover,
                  child: SizedBox(width: controller.value.size.width, height: controller.value.size.height, child: VideoPlayer(controller)),
                )
              : (media.thumbnailUrl != null ? CachedNetworkImage(imageUrl: media.thumbnailUrl!, fit: BoxFit.cover, width: double.infinity, height: double.infinity) : const LoadingView()),
        ),
      );
    }
    return GestureDetector(
      onDoubleTap: () => _toggleLike(doubleTap: true),
      child: CachedNetworkImage(
        imageUrl: media.fileUrl ?? '',
        fit: BoxFit.cover,
        width: double.infinity,
        height: double.infinity,
        placeholder: (_, __) => Container(color: AppColors.bg2),
        errorWidget: (_, __, ___) => Container(color: AppColors.bg2),
      ),
    );
  }

  Widget _badge(String text) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
        decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.5), borderRadius: BorderRadius.circular(20)),
        child: Text(text, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
      );

  Widget _actionButton({required IconData icon, Color color = Colors.white, required String label, VoidCallback? onTap, bool iconOnly = false}) {
    return GestureDetector(
      onTap: onTap,
      child: Column(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.12), shape: BoxShape.circle),
            child: Icon(icon, color: color, size: 22),
          ),
          if (!iconOnly) ...[
            const SizedBox(height: 4),
            Text(label, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
          ],
        ],
      ),
    );
  }
}

class _YoutubePlayer extends StatefulWidget {
  final String videoId;
  final bool muted;
  const _YoutubePlayer({super.key, required this.videoId, required this.muted});

  @override
  State<_YoutubePlayer> createState() => _YoutubePlayerState();
}

class _YoutubePlayerState extends State<_YoutubePlayer> {
  late final WebViewController _controller;

  @override
  void initState() {
    super.initState();
    final id = widget.videoId;
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.black)
      ..loadRequest(Uri.parse(
        'https://www.youtube.com/embed/$id?autoplay=1&mute=${widget.muted ? 1 : 0}&playsinline=1&loop=1&rel=0&playlist=$id',
      ));
  }

  @override
  Widget build(BuildContext context) {
    return WebViewWidget(controller: _controller);
  }
}

class _CommentSheet extends StatefulWidget {
  final int postId;
  final ApiClient api;
  final void Function(int count) onAdded;
  const _CommentSheet({required this.postId, required this.api, required this.onAdded});

  @override
  State<_CommentSheet> createState() => _CommentSheetState();
}

class _CommentSheetState extends State<_CommentSheet> {
  final _message = TextEditingController();
  final _name = TextEditingController();
  List<Map<String, dynamic>> _comments = [];
  bool _loading = true;
  bool _posting = false;

  @override
  void initState() {
    super.initState();
    _name.text = '';
    _load();
  }

  Future<void> _load() async {
    try {
      final list = await widget.api.fetchComments(widget.postId);
      if (mounted) {
        setState(() {
          _comments = list;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _submit() async {
    final message = _message.text.trim();
    if (message.isEmpty || _posting) return;
    setState(() => _posting = true);
    try {
      await widget.api.postComment(postId: widget.postId, name: _name.text.trim().isEmpty ? null : _name.text.trim(), message: message);
      widget.onAdded(_comments.length + 1);
      _message.clear();
      await _load();
    } catch (_) {
    } finally {
      if (mounted) setState(() => _posting = false);
    }
  }

  @override
  void dispose() {
    _message.dispose();
    _name.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: Container(
        height: MediaQuery.of(context).size.height * 0.62,
        decoration: const BoxDecoration(
          color: Color(0xFF181818),
          borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
        ),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                children: [
                  const Text('Comments', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 15)),
                  const Spacer(),
                  GestureDetector(onTap: () => Navigator.pop(context), child: const Icon(Icons.close, color: Colors.white70, size: 20)),
                ],
              ),
            ),
            const Divider(height: 1, color: Color(0xFF2A2A2A)),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator(color: AppColors.gold))
                  : _comments.isEmpty
                      ? const Center(child: Text('Be the first to comment. 💬', style: TextStyle(color: Colors.white54)))
                      : ListView.builder(
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                          itemCount: _comments.length,
                          itemBuilder: (_, i) {
                            final c = _comments[i];
                            return Padding(
                              padding: const EdgeInsets.symmetric(vertical: 10),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Container(
                                    width: 30,
                                    height: 30,
                                    decoration: const BoxDecoration(color: Color(0xFF262626), shape: BoxShape.circle),
                                    alignment: Alignment.center,
                                    child: Text(
                                      _initial(c['name'] as String?),
                                      style: const TextStyle(color: AppColors.goldSoft, fontWeight: FontWeight.w700, fontSize: 12),
                                    ),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        if (c['name'] != null)
                                          Text(c['name'] as String, style: const TextStyle(color: AppColors.goldSoft, fontWeight: FontWeight.w700, fontSize: 13)),
                                        Text(c['message'] as String? ?? '', style: const TextStyle(color: Colors.white, fontSize: 13.5)),
                                        Text(c['created_at'] as String? ?? '', style: const TextStyle(color: Colors.white38, fontSize: 11)),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            );
                          },
                        ),
            ),
            const Divider(height: 1, color: Color(0xFF2A2A2A)),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                children: [
                  TextField(
                    controller: _name,
                    maxLength: 100,
                    style: const TextStyle(color: Colors.white, fontSize: 13),
                    decoration: _inputDeco('Your name (optional)'),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Expanded(
                        child: TextField(
                          controller: _message,
                          maxLines: 2,
                          maxLength: 1000,
                          style: const TextStyle(color: Colors.white, fontSize: 13.5),
                          decoration: _inputDeco('Add a comment…'),
                        ),
                      ),
                      const SizedBox(width: 8),
                      IconButton(
                        onPressed: _posting ? null : _submit,
                        icon: const Icon(Icons.send, color: AppColors.gold),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  InputDecoration _inputDeco(String hint) {
    return InputDecoration(
      hintText: hint,
      counterText: '',
      hintStyle: const TextStyle(color: Colors.white38, fontSize: 13),
      filled: true,
      fillColor: const Color(0xFF262626),
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
    );
  }
}

String _initial(String? name) {
  final s = (name ?? '').trim();
  return s.isEmpty ? 'C' : s[0].toUpperCase();
}
