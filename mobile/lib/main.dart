import 'package:flutter/material.dart';
import 'app.dart';
import 'services/in_app_update_service.dart';
import 'services/push_service.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  // Start Firebase push + check for Play in-app updates in the background —
  // neither blocks first paint.
  PushService.init();
  InAppUpdateService.check();
  runApp(const ChurchMediaApp());
}
