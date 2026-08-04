import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../models/models.dart';
import '../services/api_client.dart';
import '../theme/app_theme.dart';
import '../widgets/common.dart';

/// Opens the livestream in an external player (YouTube/Facebook app or
/// browser) rather than embedding a WebView, keeping the app dependency-light.
class LiveScreen extends StatefulWidget {
  const LiveScreen({super.key});
  @override
  State<LiveScreen> createState() => _LiveScreenState();
}

class _LiveScreenState extends State<LiveScreen> {
  ChurchSettings? _settings;

  @override
  void initState() {
    super.initState();
    ApiClient().fetchSettings().then((s) {
      if (mounted) setState(() => _settings = s);
    });
  }

  @override
  Widget build(BuildContext context) {
    final s = _settings;
    if (s == null) return const Scaffold(body: LoadingView());
    final isLive = s.livestreamIsLive;

    return Scaffold(
      appBar: AppBar(title: const Text('Live')),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          if (isLive)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              decoration: BoxDecoration(color: AppColors.danger.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(20)),
              child: const Row(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.circle, color: AppColors.danger, size: 10),
                SizedBox(width: 8),
                Text('LIVE NOW', style: TextStyle(color: AppColors.danger, fontWeight: FontWeight.w800, fontSize: 12)),
              ]),
            ),
          const SizedBox(height: 16),
          Text(isLive ? 'We Are Live' : 'Watch Online', style: Theme.of(context).textTheme.headlineMedium),
          const SizedBox(height: 8),
          Text(
            isLive ? "Join the service right now — glad you're here." : 'Check back at service time, or catch up on our channel.',
            style: const TextStyle(color: AppColors.inkDim),
          ),
          const SizedBox(height: 24),
          if (s.livestreamEmbedUrl != null)
            ElevatedButton.icon(
              onPressed: () => launchUrl(Uri.parse(s.livestreamEmbedUrl!), mode: LaunchMode.externalApplication),
              icon: const Icon(Icons.play_circle_fill),
              label: const Text('Watch Stream'),
            )
          else
            const EmptyState(message: 'No stream configured yet.'),
          if (s.serviceTimes.isNotEmpty) ...[
            const SizedBox(height: 30),
            Text('Service Times', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            ...s.serviceTimes.map((st) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Row(children: [
                    Expanded(child: Text(st.label, style: const TextStyle(color: AppColors.inkDim))),
                    Text(st.time, style: const TextStyle(color: AppColors.goldSoft, fontWeight: FontWeight.w600)),
                  ]),
                )),
          ],
        ],
      ),
    );
  }
}
