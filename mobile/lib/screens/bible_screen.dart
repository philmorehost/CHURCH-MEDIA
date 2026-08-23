import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../services/api_client.dart';
import '../services/bible_local_store.dart';
import '../services/offline_bible_service.dart';
import '../services/share_service.dart';
import '../theme/app_theme.dart';

/// Highlight color palette (key stored in the local DB).
const Map<String, Color> _highlightColors = {
  'yellow': Color(0xFFFFF59D),
  'green': Color(0xFFA5D6A7),
  'blue': Color(0xFF90CAF9),
  'pink': Color(0xFFF48FB1),
  'purple': Color(0xFFCE93D8),
};

const List<String> _onlineVersions = ['NIV', 'NLT', 'NKJV'];

class BibleScreen extends StatefulWidget {
  const BibleScreen({super.key});

  @override
  State<BibleScreen> createState() => _BibleScreenState();
}

class _BibleScreenState extends State<BibleScreen> {
  final _api = ApiClient();
  final _verseController = TextEditingController();
  final _chapterController = TextEditingController(text: '1');

  String _selectedVersion = 'KJV';
  String _selectedLang = 'en';
  String _selectedBook = 'Genesis';
  int _selectedChapter = 1;

  List<String> _books = [];
  List<({int verse, String text})> _passage = [];
  Map<int, String> _highlights = {};
  Map<int, String> _notes = {};
  Set<int> _bookmarked = {};

  String _reference = 'Choose a passage';
  String _translation = '';
  String _errorMessage = '';
  bool _isLoading = false;
  bool _showSearch = true;
  double _fontSize = 18;
  OfflineVerseOfDay _verseOfDay = const OfflineVerseOfDay(text: '', reference: '');

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    _verseOfDay = OfflineBibleService.instance.verseOfDay();
    final font = await BibleLocalStore.instance.getSetting('bible_font_size');
    if (font != null) {
      final v = double.tryParse(font);
      if (v != null && v >= 12 && v <= 28) _fontSize = v;
    }
    final books = await OfflineBibleService.instance.books('kjv');
    if (!mounted) return;
    setState(() {
      _books = books.map((b) => b.name).toList();
      if (_books.isNotEmpty) _selectedBook = _books.first;
    });
    await _restorePosition();
    if (mounted) await _read();
  }

  Future<void> _restorePosition() async {
    final pos = await BibleLocalStore.instance.positionFor(_selectedVersion);
    if (pos == null) return;
    final idx = _books.indexWhere((n) => n.toLowerCase() == pos.book.toLowerCase());
    if (idx < 0) return;
    setState(() {
      _selectedBook = _books[idx];
      _selectedChapter = pos.chapter;
      _chapterController.text = '${pos.chapter}';
    });
    if (pos.verse > 0) _verseController.text = '${pos.verse}';
  }

  @override
  void dispose() {
    _verseController.dispose();
    _chapterController.dispose();
    super.dispose();
  }

  bool get _offline => OfflineBibleService.isOffline(_selectedVersion);

  Future<void> _read() async {
    final vText = _verseController.text.trim();
    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });
    try {
      if (_offline) {
        await _readOffline(vText);
      } else {
        await _readOnline(vText);
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _errorMessage = _offline
              ? 'An error occurred while reading scripture.'
              : 'Could not reach the online Bible. Check your connection or switch to KJV (offline).';
        });
      }
    }
  }

  Future<void> _readOffline(String vText) async {
    final key = _selectedVersion.toLowerCase();
    final books = await OfflineBibleService.instance.books(key);
    final idx = books.indexWhere((b) => b.name.toLowerCase() == _selectedBook.toLowerCase());
    if (idx < 0) {
      if (mounted) setState(() { _isLoading = false; _errorMessage = 'Book not found.'; });
      return;
    }
    final verses = await OfflineBibleService.instance.chapterVerses(key, idx, _selectedChapter);
    if (verses.isEmpty) {
      if (mounted) setState(() { _isLoading = false; _errorMessage = 'Chapter not found.'; });
      return;
    }
    final start = vText.isNotEmpty ? (int.tryParse(vText) ?? 1) : 1;
    final shown = vText.isNotEmpty
        ? (start <= verses.length ? [verses[start - 1]] : <String>[])
        : verses;
    final passage = [for (var i = 0; i < shown.length; i++) (verse: start + i, text: shown[i])];

    final highlights = await BibleLocalStore.instance.highlightsForChapter(_selectedBook, _selectedChapter);
    final notes = await BibleLocalStore.instance.notesForChapter(_selectedBook, _selectedChapter);
    final bookmarked = await BibleLocalStore.instance.bookmarkedVerses(_selectedBook, _selectedChapter);
    if (!mounted) return;
    setState(() {
      _passage = passage;
      _highlights = highlights;
      _notes = notes;
      _bookmarked = bookmarked;
      _reference = '$_selectedBook $_selectedChapter${vText.isNotEmpty ? ':$start' : ''}';
      _translation = OfflineBibleService.versions[key] ?? _selectedVersion;
      _isLoading = false;
      _showSearch = false;
    });
    await BibleLocalStore.instance.savePosition(_selectedVersion, _selectedBook, _selectedChapter, start);
  }

  Future<void> _readOnline(String vText) async {
    final response = await _api.fetchBible(
      book: _selectedBook,
      chapter: _selectedChapter,
      version: _selectedVersion,
      lang: _selectedLang,
      verse: vText.isEmpty ? null : vText,
    );
    if (response['error'] != null) {
      if (mounted) setState(() { _isLoading = false; _errorMessage = response['error'] as String; });
      return;
    }
    final verses = (response['verses'] as List<dynamic>? ?? []);
    final passage = <({int verse, String text})>[
      for (final v in verses) (verse: int.tryParse('${v['verse']}') ?? 1, text: '${v['text']}'),
    ];
    final highlights = await BibleLocalStore.instance.highlightsForChapter(_selectedBook, _selectedChapter);
    final notes = await BibleLocalStore.instance.notesForChapter(_selectedBook, _selectedChapter);
    final bookmarked = await BibleLocalStore.instance.bookmarkedVerses(_selectedBook, _selectedChapter);
    if (!mounted) return;
    setState(() {
      _passage = passage;
      _highlights = highlights;
      _notes = notes;
      _bookmarked = bookmarked;
      _reference = response['reference'] as String? ?? '$_selectedBook $_selectedChapter';
      _translation = response['translation'] as String? ?? '';
      _isLoading = false;
      _showSearch = false;
    });
    final start = vText.isNotEmpty ? (int.tryParse(vText) ?? 1) : 1;
    await BibleLocalStore.instance.savePosition(_selectedVersion, _selectedBook, _selectedChapter, start);
  }

  Future<void> _refreshChapterMeta() async {
    final highlights = await BibleLocalStore.instance.highlightsForChapter(_selectedBook, _selectedChapter);
    final notes = await BibleLocalStore.instance.notesForChapter(_selectedBook, _selectedChapter);
    final bookmarked = await BibleLocalStore.instance.bookmarkedVerses(_selectedBook, _selectedChapter);
    if (mounted) setState(() { _highlights = highlights; _notes = notes; _bookmarked = bookmarked; });
  }

  void _openSearchPanel() {
    setState(() => _showSearch = true);
  }

  void _onVersionChanged(String v) {
    setState(() {
      _selectedVersion = v;
      _selectedLang = 'en';
    });
    _read();
  }

  void _navChapter(int delta) {
    final next = _selectedChapter + delta;
    if (next < 1) return;
    setState(() {
      _selectedChapter = next;
      _chapterController.text = '$next';
    });
    _read();
  }

  Future<void> _openSearchScreen() async {
    final versionKey = _offline ? _selectedVersion.toLowerCase() : 'kjv';
    final result = await Navigator.push<({String book, int chapter, int verse})>(
      context,
      MaterialPageRoute(builder: (_) => _BibleSearchScreen(versionKey: versionKey)),
    );
    if (result != null && mounted) {
      setState(() {
        _selectedBook = result.book;
        _selectedChapter = result.chapter;
        _chapterController.text = '${result.chapter}';
        _verseController.text = '${result.verse}';
        _showSearch = true;
      });
      await _read();
    }
  }

  Future<void> _showFontSizeSheet() async {
    await showModalBottomSheet(
      context: context,
      showDragHandle: true,
      builder: (context) => StatefulBuilder(builder: (context, setSheetState) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Font size', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
              const SizedBox(height: 4),
              Row(children: [
                const Icon(Icons.format_size),
                Expanded(
                  child: Slider(
                    min: 12,
                    max: 28,
                    divisions: 8,
                    value: _fontSize,
                    onChanged: (v) {
                      setSheetState(() {});
                      setState(() => _fontSize = v);
                      BibleLocalStore.instance.setSetting('bible_font_size', v.round().toString());
                    },
                  ),
                ),
                Text('${_fontSize.round()}'),
              ]),
              const SizedBox(height: 4),
              Text('The quick brown fox', style: TextStyle(fontSize: _fontSize)),
            ]),
          ),
        );
      }),
    );
  }

  Future<void> _showVerseActions(int verse, String text) async {
    final bookmarked = _bookmarked.contains(verse);
    final existingNote = _notes[verse];
    final currentColor = _highlights[verse];
    final reference = '$_selectedBook $_selectedChapter:$verse';

    await showModalBottomSheet(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(8, 0, 8, 12),
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Text(reference, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                child: Text(text, maxLines: 3, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AppColors.inkDim)),
              ),
              const Divider(height: 16),
              ListTile(
                dense: true,
                leading: Icon(bookmarked ? Icons.bookmark : Icons.bookmark_border, color: AppColors.gold),
                title: Text(bookmarked ? 'Remove bookmark' : 'Bookmark'),
                onTap: () async {
                  Navigator.pop(sheetContext);
                  if (bookmarked) {
                    await BibleLocalStore.instance.removeBookmark(_selectedBook, _selectedChapter, verse);
                  } else {
                    await BibleLocalStore.instance.addBookmark(_selectedBook, _selectedChapter, verse, _selectedVersion);
                  }
                  await _refreshChapterMeta();
                },
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                child: Row(children: [
                  const Icon(Icons.brush, size: 24),
                  const SizedBox(width: 16),
                  const Expanded(child: Text('Highlight', style: TextStyle(fontWeight: FontWeight.w600))),
                  for (final e in _highlightColors.entries)
                    Padding(
                      padding: const EdgeInsets.only(left: 6),
                      child: GestureDetector(
                        onTap: () async {
                          Navigator.pop(sheetContext);
                          await BibleLocalStore.instance.setHighlight(_selectedBook, _selectedChapter, verse, e.key);
                          await _refreshChapterMeta();
                        },
                        child: CircleAvatar(
                          radius: 12,
                          backgroundColor: e.value,
                          child: currentColor == e.key ? const Icon(Icons.check, size: 14, color: Colors.black) : null,
                        ),
                      ),
                    ),
                  const SizedBox(width: 6),
                  GestureDetector(
                    onTap: () async {
                      Navigator.pop(sheetContext);
                      await BibleLocalStore.instance.setHighlight(_selectedBook, _selectedChapter, verse, null);
                      await _refreshChapterMeta();
                    },
                    child: const CircleAvatar(radius: 12, child: Icon(Icons.clear, size: 14, color: Colors.grey)),
                  ),
                ]),
              ),
              ListTile(
                dense: true,
                leading: const Icon(Icons.sticky_note_2_outlined),
                title: Text(existingNote != null && existingNote.isNotEmpty ? 'Edit note' : 'Add note'),
                onTap: () { Navigator.pop(sheetContext); _editNote(verse); },
              ),
              ListTile(
                dense: true,
                leading: const Icon(Icons.copy),
                title: const Text('Copy'),
                onTap: () async {
                  Navigator.pop(sheetContext);
                  await Clipboard.setData(ClipboardData(text: '$reference\n\n$text'));
                  if (mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Verse copied'), duration: Duration(seconds: 1)));
                  }
                },
              ),
              ListTile(
                dense: true,
                leading: const Icon(Icons.ios_share),
                title: const Text('Share'),
                onTap: () {
                  Navigator.pop(sheetContext);
                  ShareService.share(text: '$reference\n\n$text');
                },
              ),
            ]),
          ),
        );
      },
    );
  }

  Future<void> _editNote(int verse) async {
    final controller = TextEditingController(text: _notes[verse] ?? '');
    final saved = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Note'),
        content: TextField(controller: controller, maxLines: 4, autofocus: true, decoration: const InputDecoration(hintText: 'Write a note...')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(context, controller.text.trim()), child: const Text('Save')),
        ],
      ),
    );
    if (saved != null && mounted) {
      await BibleLocalStore.instance.saveNote(_selectedBook, _selectedChapter, verse, saved);
      await _refreshChapterMeta();
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Holy Bible'),
        centerTitle: true,
        actions: [
          IconButton(icon: const Icon(Icons.search), tooltip: 'Search', onPressed: _openSearchScreen),
          IconButton(icon: const Icon(Icons.text_fields), tooltip: 'Font size', onPressed: _showFontSizeSheet),
          if (!_showSearch)
            IconButton(icon: const Icon(Icons.my_location), tooltip: 'Open a new passage', onPressed: _openSearchPanel),
        ],
      ),
      body: Column(children: [
        AnimatedSize(
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeInOut,
          alignment: Alignment.topCenter,
          child: _showSearch ? _searchCard() : const SizedBox(width: double.infinity, height: 0),
        ),
        const SizedBox(height: 4),
        Expanded(child: _buildContent(theme)),
        if (_passage.isNotEmpty && !_showSearch)
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 6, 16, 8),
              child: Row(children: [
                Expanded(
                  child: OutlinedButton.icon(
                    icon: const Icon(Icons.chevron_left),
                    label: const Text('Previous', overflow: TextOverflow.ellipsis),
                    onPressed: () => _navChapter(-1),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton.icon(
                    icon: const Icon(Icons.chevron_right),
                    label: const Text('Next', overflow: TextOverflow.ellipsis),
                    onPressed: () => _navChapter(1),
                  ),
                ),
              ]),
            ),
          ),
      ]),
    );
  }

  Widget _searchCard() {
    return Card(
      elevation: 2,
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(children: [
          Row(children: [
            Expanded(
              child: DropdownButtonFormField<String>(
                value: _selectedVersion,
                decoration: const InputDecoration(labelText: 'Version', isDense: true),
                items: [
                  for (final v in OfflineBibleService.versions.values) DropdownMenuItem(value: v, child: Text('$v (Offline)')),
                  for (final v in _onlineVersions) DropdownMenuItem(value: v, child: Text('$v (Online)')),
                ],
                onChanged: (val) { if (val != null) _onVersionChanged(val); },
              ),
            ),
            if (!_offline) ...[
              const SizedBox(width: 12),
              Expanded(
                child: DropdownButtonFormField<String>(
                  value: _selectedLang,
                  decoration: const InputDecoration(labelText: 'Language', isDense: true),
                  items: const [
                    DropdownMenuItem(value: 'en', child: Text('English')),
                    DropdownMenuItem(value: 'es', child: Text('Español')),
                    DropdownMenuItem(value: 'fr', child: Text('Français')),
                    DropdownMenuItem(value: 'yo', child: Text('Yorùbá')),
                    DropdownMenuItem(value: 'ig', child: Text('Igbo')),
                    DropdownMenuItem(value: 'ha', child: Text('Hausa')),
                  ],
                  onChanged: (val) { if (val != null) setState(() => _selectedLang = val); },
                ),
              ),
            ],
          ]),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(
              flex: 2,
              child: DropdownButtonFormField<String>(
                value: _selectedBook,
                decoration: const InputDecoration(labelText: 'Book', isDense: true),
                items: _books.map((b) => DropdownMenuItem(value: b, child: Text(b, overflow: TextOverflow.ellipsis))).toList(),
                onChanged: (val) { if (val != null) setState(() => _selectedBook = val); },
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: TextFormField(
                controller: _chapterController,
                decoration: const InputDecoration(labelText: 'Chapter', isDense: true),
                keyboardType: TextInputType.number,
                onChanged: (val) => setState(() => _selectedChapter = int.tryParse(val) ?? 1),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: TextFormField(
                controller: _verseController,
                decoration: const InputDecoration(labelText: 'Verse (optional)', hintText: 'All', isDense: true),
                keyboardType: TextInputType.number,
              ),
            ),
          ]),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(icon: const Icon(Icons.menu_book), label: const Text('Read'), onPressed: _read),
          ),
          const SizedBox(height: 4),
          const Text('Leave Verse empty to read the whole chapter from verse 1.', style: TextStyle(fontSize: 11, color: AppColors.inkFaint)),
        ]),
      ),
    );
  }

  Widget _buildContent(ThemeData theme) {
    if (_isLoading) return const Center(child: CircularProgressIndicator());
    if (_errorMessage.isNotEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off, size: 40, color: AppColors.inkFaint),
            const SizedBox(height: 12),
            Text(_errorMessage, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            if (!_offline)
              OutlinedButton(onPressed: () => _onVersionChanged('KJV'), child: const Text('Read KJV offline')),
          ]),
        ),
      );
    }
    if (_passage.isEmpty) return _emptyState(theme);
    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
      itemCount: _passage.length + 1,
      itemBuilder: (context, index) {
        if (index == 0) {
          return Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Row(children: [
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(_reference, style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold)),
                  if (_translation.isNotEmpty) Text(_translation, style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline)),
                ]),
              ),
              IconButton(icon: const Icon(Icons.my_location), tooltip: 'Open a new passage', onPressed: _openSearchPanel),
            ]),
          );
        }
        final p = _passage[index - 1];
        return _verseTile(theme, p.verse, p.text);
      },
    );
  }

  Widget _verseTile(ThemeData theme, int verse, String text) {
    final color = _highlights[verse];
    return GestureDetector(
      onLongPress: () => _showVerseActions(verse, text),
      child: Container(
        color: color != null ? (_highlightColors[color] ?? Colors.yellow).withValues(alpha: 0.35) : null,
        padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 4),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(
            child: RichText(
              text: TextSpan(
                style: TextStyle(color: theme.colorScheme.onSurface, fontSize: _fontSize, height: 1.6),
                children: [
                  TextSpan(
                    text: '$verse ',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: _fontSize * 0.65, color: theme.colorScheme.outline),
                  ),
                  TextSpan(text: text),
                ],
              ),
            ),
          ),
          if (_bookmarked.contains(verse) || _notes.containsKey(verse))
            Padding(
              padding: const EdgeInsets.only(left: 6, top: 2),
              child: Column(children: [
                if (_bookmarked.contains(verse)) const Icon(Icons.bookmark, size: 13, color: AppColors.gold),
                if (_notes.containsKey(verse)) const Padding(padding: EdgeInsets.only(top: 2), child: Icon(Icons.sticky_note_2_outlined, size: 13, color: AppColors.inkFaint)),
              ]),
            ),
        ]),
      ),
    );
  }

  Widget _emptyState(ThemeData theme) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.menu_book, size: 44, color: AppColors.inkFaint),
          const SizedBox(height: 12),
          Text(_verseOfDay.reference, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          const SizedBox(height: 8),
          Text('“${_verseOfDay.text}”', textAlign: TextAlign.center, style: const TextStyle(fontStyle: FontStyle.italic, fontSize: 15, color: AppColors.inkDim)),
          const SizedBox(height: 20),
          const Text('Select a book, chapter, and verse above to begin reading.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.inkFaint)),
        ]),
      ),
    );
  }
}

/// Full-screen offline Bible search. Returns a record via Navigator.pop.
class _BibleSearchScreen extends StatefulWidget {
  final String versionKey;
  const _BibleSearchScreen({required this.versionKey});

  @override
  State<_BibleSearchScreen> createState() => _BibleSearchScreenState();
}

class _BibleSearchScreenState extends State<_BibleSearchScreen> {
  final _controller = TextEditingController();
  List<OfflineSearchHit> _results = [];
  bool _searching = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _run(String q) async {
    setState(() => _searching = true);
    final r = await OfflineBibleService.instance.search(widget.versionKey, q);
    if (mounted) setState(() { _results = r; _searching = false; });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Search Bible')),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: TextField(
            controller: _controller,
            autofocus: true,
            decoration: const InputDecoration(hintText: 'Search verses...', prefixIcon: Icon(Icons.search), border: OutlineInputBorder()),
            onChanged: _run,
          ),
        ),
        Expanded(
          child: _searching
              ? const Center(child: CircularProgressIndicator())
              : _results.isEmpty
                  ? const Center(child: Padding(padding: EdgeInsets.all(20), child: Text('Type a word or phrase to search the whole Bible.')))
                  : ListView.builder(
                      itemCount: _results.length,
                      itemBuilder: (context, i) {
                        final r = _results[i];
                        return ListTile(
                          dense: true,
                          leading: const Icon(Icons.menu_book, color: AppColors.gold),
                          title: Text(r.reference, style: const TextStyle(fontWeight: FontWeight.bold)),
                          subtitle: Text(r.text, maxLines: 2, overflow: TextOverflow.ellipsis),
                          onTap: () => Navigator.pop(context, (book: r.book, chapter: r.chapter, verse: r.verse)),
                        );
                      },
                    ),
        ),
      ]),
    );
  }
}
