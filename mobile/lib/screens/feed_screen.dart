import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:share_plus/share_plus.dart';
import 'package:video_player/video_player.dart';
import 'package:visibility_detector/visibility_detector.dart';
import '../models/models.dart';
import '../services/api_client.dart';
import '../theme/app_theme.dart';
import '../widgets/common.dart';

/// Vertical, full-screen, infinite-scrolling feed — behaves like posting a
/// reel: swipe up for the next post, tap a video to mute/unmute, like/share
/// from the right-hand action rail. Mirrors views/feed.php + assets/js/feed.js.
class FeedScreen extends StatefulWidget {
  const FeedScreen({super.key});
  @override
  State<FeedScreen> createState() => _FeedScreenState();
}

class _FeedScreenState extends State<FeedScreen> {
  final _api = ApiClient();
  final _pageController = PageController();
  final List<Post> _posts = [];
  List<Category> _categories = [];
  String? _activeCategory;
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

  Future<void> _loadMore() async {
    if (_loading || !_hasMore) return;
    setState(() => _loading = true);
    try {
      final result = await _api.fetchFeed(page: _page, category: _activeCategory);
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
            if (_categories.isNotEmpty) _buildChips(),
            Expanded(
              child: _posts.isEmpty
                  ? (_loading ? const LoadingView() : const EmptyState(message: 'No posts in the feed yet.'))
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
                      ),
                    ),
            ),
          ],
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
  const _FeedSlide({required this.post, required this.api, required this.onLikeChanged});

  @override
  State<_FeedSlide> createState() => _FeedSlideState();
}

class _FeedSlideState extends State<_FeedSlide> {
  VideoPlayerController? _videoController;
  int _mediaIndex = 0;
  bool _muted = true;
  bool _liking = false;

  MediaItem? get _activeMedia => widget.post.mediaItems.isEmpty ? null : widget.post.mediaItems[_mediaIndex];

  @override
  void dispose() {
    _videoController?.dispose();
    super.dispose();
  }

  void _setupVideoIfNeeded() {
    final media = _activeMedia;
    if (media == null || media.type != 'video' || media.fileUrl == null) return;
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

  Future<void> _toggleLike() async {
    if (_liking) return;
    setState(() => _liking = true);
    try {
      final result = await widget.api.toggleLike(widget.post.id);
      widget.onLikeChanged(result.liked, result.likesCount);
      setState(() {
        widget.post.likedByViewer = result.liked;
      });
    } catch (_) {
    } finally {
      if (mounted) setState(() => _liking = false);
    }
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
            child: _badge(post.postType == 'vertical_reel' ? '▶ Reel' : (post.postType == 'carousel' ? '⧉ Carousel' : 'Photo')),
          ),
          Positioned(
            left: 16,
            right: 90,
            bottom: 20,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(post.authorName, style: const TextStyle(color: AppColors.goldSoft, fontWeight: FontWeight.w700, fontSize: 13.5)),
                const SizedBox(height: 6),
                if (post.caption != null && post.caption!.isNotEmpty)
                  Text(post.caption!, style: const TextStyle(color: Colors.white, fontSize: 14), maxLines: 3, overflow: TextOverflow.ellipsis),
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
                  onTap: _toggleLike,
                ),
                const SizedBox(height: 20),
                _actionButton(icon: Icons.remove_red_eye_outlined, label: _formatCount(post.viewsCount), onTap: null),
                const SizedBox(height: 20),
                _actionButton(
                  icon: Icons.ios_share,
                  label: 'Share',
                  onTap: () => SharePlus.instance.share(ShareParams(text: post.caption ?? 'Check this out', uri: Uri.parse('${ApiClient.baseUrl}/feed'))),
                ),
              ],
            ),
          ),
          if (widget.post.mediaItems.length > 1) ...[
            Positioned.fill(
              child: Row(children: [
                Expanded(child: GestureDetector(onTap: _prevMedia, behavior: HitTestBehavior.translucent)),
                Expanded(flex: 2, child: const SizedBox()),
                Expanded(child: GestureDetector(onTap: _nextMedia, behavior: HitTestBehavior.translucent)),
              ]),
            ),
          ],
        ],
      ),
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

    if (media.type == 'video') {
      final controller = _videoController;
      return GestureDetector(
        onTap: _toggleMute,
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
    return CachedNetworkImage(
      imageUrl: media.fileUrl ?? '',
      fit: BoxFit.cover,
      width: double.infinity,
      height: double.infinity,
      placeholder: (_, __) => Container(color: AppColors.bg2),
      errorWidget: (_, __, ___) => Container(color: AppColors.bg2),
    );
  }

  Widget _badge(String text) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
        decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.5), borderRadius: BorderRadius.circular(20)),
        child: Text(text, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
      );

  Widget _actionButton({required IconData icon, Color color = Colors.white, required String label, VoidCallback? onTap}) {
    return GestureDetector(
      onTap: onTap,
      child: Column(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.12), shape: BoxShape.circle),
            child: Icon(icon, color: color, size: 24),
          ),
          const SizedBox(height: 4),
          Text(label, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}
