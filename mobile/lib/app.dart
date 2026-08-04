import 'package:flutter/material.dart';
import 'screens/events_screen.dart';
import 'screens/feed_screen.dart';
import 'screens/home_screen.dart';
import 'screens/more_screen.dart';
import 'screens/sermons_screen.dart';
import 'theme/app_theme.dart';

class ChurchMediaApp extends StatelessWidget {
  const ChurchMediaApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Church Media',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.dark,
      darkTheme: AppTheme.dark,
      themeMode: ThemeMode.dark,
      home: const RootShell(),
    );
  }
}

/// Bottom-nav shell — Home / Feed(Reels) / Events / Sermons / More.
/// No login anywhere: every screen here is publicly viewable.
class RootShell extends StatefulWidget {
  const RootShell({super.key});
  @override
  State<RootShell> createState() => _RootShellState();
}

class _RootShellState extends State<RootShell> {
  int _index = 0;

  void _goTo(int index) => setState(() => _index = index);

  @override
  Widget build(BuildContext context) {
    final screens = [
      HomeScreen(onNavigate: _goTo),
      const FeedScreen(),
      const EventsScreen(),
      const SermonsScreen(),
      const MoreScreen(),
    ];

    return Scaffold(
      body: IndexedStack(index: _index, children: screens),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _index,
        onTap: _goTo,
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.home_outlined), activeIcon: Icon(Icons.home), label: 'Home'),
          BottomNavigationBarItem(icon: Icon(Icons.play_circle_outline), activeIcon: Icon(Icons.play_circle), label: 'Feed'),
          BottomNavigationBarItem(icon: Icon(Icons.event_outlined), activeIcon: Icon(Icons.event), label: 'Events'),
          BottomNavigationBarItem(icon: Icon(Icons.menu_book_outlined), activeIcon: Icon(Icons.menu_book), label: 'Sermons'),
          BottomNavigationBarItem(icon: Icon(Icons.more_horiz), label: 'More'),
        ],
      ),
    );
  }
}
