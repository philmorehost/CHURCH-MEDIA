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
  bool _isLoading = false;
  List<dynamic> _verses = [];
  String _errorMessage = '';

  final List<String> _versions = ['KJV', 'NIV', 'NLT', 'NKJV'];
  final List<String> _books = [
    'Genesis', 'Exodus', 'Leviticus', 'Numbers', 'Deuteronomy',
    'Isaiah', 'Jeremiah', 'John', 'Romans', 'Revelation'
  ];

  Future<void> _fetchScripture() async {
    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });

    try {
      final apiClient = ApiClient();
      final response = await apiClient.get('/api/bible.php', queryParameters: {
        'book': _selectedBook,
        'chapter': _selectedChapter.toString(),
        'version': _selectedVersion,
        'lang': _selectedLang,
      });

      if (response['error'] != null) {
        setState(() => _errorMessage = response['error']);
      } else {
        // Handle both Bible-Api.com (verses list) and API.Bible formats
        if (response['verses'] != null) {
          setState(() => _verses = response['verses']);
        } else if (response['content'] != null) {
          // Simplified: treat content as a single verse for display
          setState(() => _verses = [{'verse': '1', 'text': response['content']}]);
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
  Widget build(BuildContext context) {
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
                      ? Center(child: Text(_errorMessage, style: const TextStyle(color: Colors.red)))
                      : _verses.isEmpty
                          ? const Center(child: Text('Select a passage and press Read'))
                          : ListView.builder(
                              itemCount: _verses.length,
                              itemBuilder: (context, index) {
                                final v = _verses[index];
                                return Padding(
                                  padding: const EdgeInsets.symmetric(vertical: 8.0),
                                  child: RichText(
                                    text: TextSpan(
                                      style: const TextStyle(color: Colors.black, fontSize: 18, height: 1.6),
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
