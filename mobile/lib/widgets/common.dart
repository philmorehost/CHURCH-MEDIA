import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

class LoadingView extends StatelessWidget {
  const LoadingView({super.key});
  @override
  Widget build(BuildContext context) => const Center(child: CircularProgressIndicator(color: AppColors.gold));
}

class EmptyState extends StatelessWidget {
  final String message;
  const EmptyState({super.key, required this.message});
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.all(40),
        child: Center(
          child: Text(message, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.inkFaint)),
        ),
      );
}

class SectionHeader extends StatelessWidget {
  final String eyebrow;
  final String title;
  const SectionHeader({super.key, required this.eyebrow, required this.title});
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(20, 28, 20, 14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(eyebrow.toUpperCase(),
                style: const TextStyle(color: AppColors.goldSoft, fontSize: 12, fontWeight: FontWeight.w700, letterSpacing: 1.2)),
            const SizedBox(height: 6),
            Text(title, style: Theme.of(context).textTheme.headlineSmall),
          ],
        ),
      );
}

class GoldButton extends StatelessWidget {
  final String label;
  final VoidCallback? onPressed;
  const GoldButton({super.key, required this.label, this.onPressed});
  @override
  Widget build(BuildContext context) => ElevatedButton(onPressed: onPressed, child: Text(label));
}
