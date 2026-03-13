import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';

/// Service for persisting state across app restarts
class StatePersistence {
  static const String _authStateKey = 'auth_state';
  static const String _userPreferencesKey = 'user_preferences';
  
  final SharedPreferences _prefs;
  
  StatePersistence(this._prefs);
  
  // Auth state persistence
  Future<void> saveAuthState(Map<String, dynamic> state) async {
    await _prefs.setString(_authStateKey, jsonEncode(state));
  }
  
  Future<Map<String, dynamic>?> loadAuthState() async {
    final stateString = _prefs.getString(_authStateKey);
    if (stateString == null) return null;
    
    try {
      return jsonDecode(stateString) as Map<String, dynamic>;
    } catch (e) {
      return null;
    }
  }
  
  Future<void> clearAuthState() async {
    await _prefs.remove(_authStateKey);
  }
  
  // User preferences persistence
  Future<void> saveUserPreferences(Map<String, dynamic> preferences) async {
    await _prefs.setString(_userPreferencesKey, jsonEncode(preferences));
  }
  
  Future<Map<String, dynamic>?> loadUserPreferences() async {
    final prefsString = _prefs.getString(_userPreferencesKey);
    if (prefsString == null) return null;
    
    try {
      return jsonDecode(prefsString) as Map<String, dynamic>;
    } catch (e) {
      return null;
    }
  }
  
  // Generic key-value storage
  Future<void> saveString(String key, String value) async {
    await _prefs.setString(key, value);
  }
  
  String? getString(String key) {
    return _prefs.getString(key);
  }
  
  Future<void> saveBool(String key, bool value) async {
    await _prefs.setBool(key, value);
  }
  
  bool? getBool(String key) {
    return _prefs.getBool(key);
  }
  
  Future<void> remove(String key) async {
    await _prefs.remove(key);
  }
  
  Future<void> clear() async {
    await _prefs.clear();
  }
}
