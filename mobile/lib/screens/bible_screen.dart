import 'package:flutter/material.dart';
import '../services/api_client.dart';

class BibleScreen extends StatefulWidget {
  const BibleScreen({super.key});

  @override
  State<BibleScreen> createState() => _BibleScreenState();
}

class _BibleScreenState extends State<BibleScreen> {
  String _selectedVersion = 'KJV';
  String _selectedLang = 'en';
  String _selectedBook = 'Genesis';
  int _selectedChapter = 1;
  final TextEditingController _verseController = TextEditingController();
  bool _isLoading = false;
  List<dynamic> _verses = [];
  String _errorMessage = '';
  String _reference = '';
  String _translation = '';

  final List<String> _versions = ['KJV', 'NIV', 'NLT', 'NKJV'];
  final List<String> _books = [
    'Genesis', 'Exodus', 'Leviticus', 'Numbers', 'Deuteronomy',
    'Joshua', 'Judges', 'Ruth', '1 Samuel', '2 Samuel',
    '1 Kings', '2 Kings', '1 Chronicles', '2 Chronicles',
    'Ezra', 'Nehemiah', 'Esther', 'Job', 'Psalms', 'Proverbs',
    'Ecclesiastes', 'Song of Solomon', 'Isaiah', 'Jeremiah', 'Lamentations',
    'Ezekiel', 'Daniel', 'Hosea', 'Joel', 'Amos', 'Obadiah', 'Jonah',
    'Micah', 'Nahum', 'Habakkuk', 'Zephaniah', 'Haggai', 'Zechariah', 'Malachi',
    'Matthew', 'Mark', 'Luke', 'John', 'Acts', 'Romans',
    '1 Corinthians', '2 Corinthians', 'Galatians', 'Ephesians', 'Philippians',
    'Colossians', '1 Thessalonians', '2 Thessalonians', '1 Timothy', '2 Timothy',
    'Titus', 'Philemon', 'Hebrews', 'James', '1 Peter', '2 Peter',
    '1 John', '2 John', '3 John', 'Jude', 'Revelation',
  ];

  Future<void> _fetchScripture() async {
    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });

    try {
      final apiClient = ApiClient();
      final verseText = _verseController.text.trim();
      final response = await apiClient.fetchBible(
        book: _selectedBook,
        chapter: _selectedChapter,
        version: _selectedVersion,
        lang: _selectedLang,
        verse: verseText.isEmpty ? null : verseText,
      );

      if (response['error'] != null) {
        setState(() => _errorMessage = response['error'] as String);
      } else {
        // Both providers return a normalized {verse, text} list.
        final verses = response['verses'];
        if (verses is List && verses.isNotEmpty) {
          setState(() {
            _verses = verses;
            _reference = (response['reference'] as String? ?? '$_selectedBook $_selectedChapter');
            _translation = response['translation'] as String? ?? '';
          });
        } else {
          setState(() => _errorMessage = 'No content found.');
        }
      }
    } catch (e) {
      setState(() => _errorMessage = 'An error occurred while fetching scripture.');
    } finally {
      setState(() => _isLoading = false);
    }
  }

  @override
  void dispose() {
    _verseController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Holy Bible'),
        centerTitle: true,
      ),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          children: [
            // Selection Controls
            Card(
              elevation: 2,
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: DropdownButtonFormField<String>(
                            value: _selectedVersion,
                            decoration: const InputDecoration(labelText: 'Version'),
                            items: _versions.map((v) => DropdownMenuItem(value: v, child: Text(v))).toList(),
                            onChanged: (val) => setState(() => _selectedVersion = val!),
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: DropdownButtonFormField<String>(
                            value: _selectedLang,
                            decoration: const InputDecoration(labelText: 'Language'),
                            items: ['en', 'es', 'fr'].map((l) => DropdownMenuItem(value: l, child: Text(l == 'en' ? 'English' : (l == 'es' ? 'Spanish' : 'French')))).toList(),
                            onChanged: (val) => setState(() => _selectedLang = val!),
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: DropdownButtonFormField<String>(
                            value: _selectedBook,
                            decoration: const InputDecoration(labelText: 'Book'),
                            items: _books.map((b) => DropdownMenuItem(value: b, child: Text(b))).toList(),
                            onChanged: (val) => setState(() => _selectedBook = val!),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: TextFormField(
                            decoration: const InputDecoration(labelText: 'Chapter'),
                            keyboardType: TextInputType.number,
                            initialValue: '1',
                            onChanged: (val) => setState(() => _selectedChapter = int.tryParse(val) ?? 1),
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: TextFormField(
                            controller: _verseController,
                            decoration: const InputDecoration(labelText: 'Verse (optional)'),
                            keyboardType: TextInputType.number,
                            hintText: 'All',
                          ),
                        ),
                        const SizedBox(width: 16),
                        ElevatedButton(
                          onPressed: _fetchScripture,
                          style: ElevatedButton.styleFrom(
                            minimumSize: const Size(100, 50),
                          ),
                          child: const Text('Read'),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),
            // Scripture Content
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : _errorMessage.isNotEmpty
                      ? Center(child: Padding(padding: const EdgeInsets.all(16), child: Text(_errorMessage, textAlign: TextAlign.center, style: const TextStyle(color: Colors.red))))
                      : _verses.isEmpty
                          ? const Center(child: Text('Select a passage and press Read'))
                          : ListView.builder(
                              itemCount: _verses.length + 1,
                              itemBuilder: (context, index) {
                                if (index == 0) {
                                  return Padding(
                                    padding: const EdgeInsets.only(bottom: 12),
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(_reference, style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold)),
                                        if (_translation.isNotEmpty)
                                          Padding(
                                            padding: const EdgeInsets.only(top: 2),
                                            child: Text(_translation, style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.outline)),
                                          ),
                                        const Divider(height: 20),
                                      ],
                                    ),
                                  );
                                }
                                final v = _verses[index - 1];
                                return Padding(
                                  padding: const EdgeInsets.symmetric(vertical: 8.0),
                                  child: RichText(
                                    text: TextSpan(
                                      style: TextStyle(color: theme.colorScheme.onSurface, fontSize: 18, height: 1.6),
                                      children: [
                                        TextSpan(
                                          text: '${v['verse']} ',
                                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.grey),
                                        ),
                                        TextSpan(text: v['text']),
                                      ],
                                    ),
                                  ),
                                );
                              },
                            ),
            ),
          ],
        ),
      ),
    );
  }
}
