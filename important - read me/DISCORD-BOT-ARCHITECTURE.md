# Discord Bot für Grid King League Management

## Bot-Architektur

### **Bot-Anwendung (Python)**
- Separate Python-Anwendung mit discord.py
- Kommuniziert über REST API mit Grid King
- Slash Commands für Benutzerinteraktion
- Automatische Event-Handling

### **Grid King API Endpoints**
- `/api/standings` - Aktuelle Championship-Standings
- `/api/drivers` - Driver-Liste mit Details
- `/api/races/upcoming` - Nächste Races
- `/api/races/recent` - Kürzliche Race-Ergebnisse
- `/api/teams` - Team-Informationen

### **Discord Bot Commands**

#### **🏆 Championship Commands**
- `/standings` - Zeigt aktuelle Championship-Tabelle
- `/standings driver <name>` - Details für spezifischen Driver
- `/standings team <name>` - Team-Standings

#### **🏁 Race Commands**
- `/nextrace` - Information über nächstes Race
- `/lastrace` - Ergebnisse des letzten Races
- `/schedule` - Kommende Race-Termine
- `/results <race_name>` - Ergebnisse eines spezifischen Races

#### **👥 Driver/Team Commands**
- `/driver <name>` - Driver-Profil und Statistiken
- `/team <name>` - Team-Informationen
- `/drivers` - Liste aller Driver

#### **📊 Statistics Commands**
- `/stats wins` - Driver mit den meisten Siegen
- `/stats poles` - Pole Position-Statistiken
- `/stats dnf` - DNF-Statistiken
- `/leaderboard` - Top 10 Championship-Standings

### **Bot Features**
- Automatische Race-Result-Posts
- Countdown zu nächstem Race
- Driver-Registration-Benachrichtigungen
- Interaktive Embeds mit Buttons
- Rolle-basierte Berechtigungen

### **Konfiguration**
- Bot Token im Grid King Admin Panel
- Discord Server/Channel-IDs
- API-Authentifizierung
- Notification-Einstellungen

### **Technische Implementierung**
```
GridKing/
├── bot/
│   ├── bot.py              # Haupt-Bot-Anwendung
│   ├── commands/
│   │   ├── standings.py    # Championship-Commands
│   │   ├── races.py        # Race-Commands
│   │   ├── drivers.py      # Driver-Commands
│   │   └── stats.py        # Statistik-Commands
│   ├── utils/
│   │   ├── api_client.py   # Grid King API Client
│   │   ├── embeds.py       # Discord Embed-Builder
│   │   └── helpers.py      # Utility-Funktionen
│   ├── config.py           # Bot-Konfiguration
│   ├── requirements.txt    # Python Dependencies
│   └── README.md           # Bot Setup-Anleitung
└── api/
    ├── index.php           # API Router
    ├── endpoints/
    │   ├── standings.php   # Championship API
    │   ├── races.php       # Race API
    │   ├── drivers.php     # Driver API
    │   └── teams.php       # Team API
    └── middleware/
        ├── auth.php        # API-Authentifizierung
        └── cors.php        # CORS-Handling
```

### **Setup-Prozess**
1. Discord Application & Bot erstellen
2. Bot Token in Grid King Admin Panel eingeben
3. Server-/Channel-IDs konfigurieren
4. Bot-Anwendung auf Server deployen
5. Slash Commands registrieren

### **Security**
- API-Key-basierte Authentifizierung
- Rate Limiting
- Input Validation
- Rolle-basierte Discord-Permissions
