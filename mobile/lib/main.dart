import 'package:flutter/material.dart';
import 'app.dart';
import 'services/push_service.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  // Start Firebase push in the background — never blocks first paint.
  PushService.init();
  runApp(const ChurchMediaApp());
}
