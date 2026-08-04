import 'dart:convert';
import 'package:http/http.dart' as http;
import '../models/models.dart';

/// Talks to the church's REST API (api/*.php on the PHP backend). No auth —
/// every endpoint here is public-read or anonymous-write, matching the
/// "front-end only, no login" requirement for this app.
///
/// Override the host per build:
///   flutter run --dart-define=API_BASE_URL=https://yourchurch.org
/// Defaults to 10.0.2.2, the Android emulator's alias for the host machine's
/// localhost — swap for your real domain (or use --dart-define) for iOS
/// simulator/device builds and for production.
class ApiClient {
  static const String _configuredBase = String.fromEnvironment('API_BASE_URL', defaultValue: '');
  static String get baseUrl => _configuredBase.isNotEmpty ? _configuredBase : 'http://10.0.2.2:8080';

  Uri _uri(String path, [Map<String, dynamic>? query]) {
    final clean = query?.map((k, v) => MapEntry(k, v?.toString())) ?? {};
    clean.removeWhere((k, v) => v == null);
    return Uri.parse('$baseUrl$path').replace(queryParameters: clean.isEmpty ? null : clean);
  }

  Future<Map<String, dynamic>> _get(String path, [Map<String, dynamic>? query]) async {
    final res = await http.get(_uri(path, query));
    return jsonDecode(res.body) as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> _post(String path, Map<String, dynamic> body) async {
    final res = await http.post(_uri(path), headers: {'Content-Type': 'application/json'}, body: jsonEncode(body));
    return jsonDecode(res.body) as Map<String, dynamic>;
  }

  Future<ChurchSettings> fetchSettings() async {
    final json = await _get('/api/settings');
    return ChurchSettings.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<List<Category>> fetchCategories() async {
    final json = await _get('/api/categories');
    return (json['data'] as List<dynamic>? ?? []).map((e) => Category.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<({List<Post> posts, bool hasMore})> fetchFeed({int page = 1, String? category}) async {
    final json = await _get('/api/feed', {'page': page, 'category': category});
    final posts = (json['data'] as List<dynamic>? ?? []).map((e) => Post.fromJson(e as Map<String, dynamic>)).toList();
    return (posts: posts, hasMore: json['has_more'] as bool? ?? false);
  }

  Future<void> pingView(int postId) => _get('/api/post', {'id': postId});

  Future<({bool liked, int likesCount})> toggleLike(int postId) async {
    final json = await _post('/api/like', {'post_id': postId});
    return (liked: json['liked'] as bool? ?? false, likesCount: json['likes_count'] as int? ?? 0);
  }

  Future<({List<ChurchEvent> events, bool hasMore})> fetchEvents({String scope = 'upcoming', int page = 1}) async {
    final json = await _get('/api/events', {'scope': scope, 'page': page});
    final events = (json['data'] as List<dynamic>? ?? []).map((e) => ChurchEvent.fromJson(e as Map<String, dynamic>)).toList();
    return (events: events, hasMore: json['has_more'] as bool? ?? false);
  }

  Future<ChurchEvent?> fetchEvent(String slug) async {
    final json = await _get('/api/events', {'slug': slug});
    if (json['status'] != 'success') return null;
    return ChurchEvent.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<Sermon?> fetchSermon(String slug) async {
    final json = await _get('/api/sermons', {'slug': slug});
    if (json['status'] != 'success') return null;
    return Sermon.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<({List<Sermon> sermons, bool hasMore})> fetchSermons({int page = 1, String? series}) async {
    final json = await _get('/api/sermons', {'page': page, 'series': series});
    final sermons = (json['data'] as List<dynamic>? ?? []).map((e) => Sermon.fromJson(e as Map<String, dynamic>)).toList();
    return (sermons: sermons, hasMore: json['has_more'] as bool? ?? false);
  }

  Future<Map<String, List<dynamic>>> search(String query) async {
    final json = await _get('/api/search', {'q': query});
    final data = json['data'] as Map<String, dynamic>? ?? {};
    return data.map((k, v) => MapEntry(k, v as List<dynamic>));
  }

  Future<List<TeamMember>> fetchTeam() async {
    final json = await _get('/api/team');
    return (json['data'] as List<dynamic>? ?? []).map((e) => TeamMember.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<List<PrayerRequest>> fetchPublicPrayers() async {
    final json = await _get('/api/prayer');
    return (json['data'] as List<dynamic>? ?? []).map((e) => PrayerRequest.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<String> submitPrayer({String? name, String? email, required String message, bool isPublic = false}) async {
    final json = await _post('/api/prayer', {'name': name, 'email': email, 'message': message, 'is_public': isPublic});
    return json['message'] as String? ?? '';
  }

  Future<String> subscribeNewsletter(String email) async {
    final json = await _post('/api/newsletter', {'email': email});
    return json['message'] as String? ?? '';
  }

  Future<String> sendContactMessage({required String name, required String email, String? subject, required String message}) async {
    final json = await _post('/api/contact', {'name': name, 'email': email, 'subject': subject, 'message': message});
    return json['message'] as String? ?? '';
  }
}
