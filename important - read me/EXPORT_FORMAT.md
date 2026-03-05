# GridKing Migration & Export Format Documentation

## Version 1.4.0 Final Export Format (LTS)

This document describes the official export format for GridKing Legacy versions 1.3.x and 1.4.x, which is compatible with the hosted GridKing v2.0 platform.

---

## Export Formats

GridKing supports three export formats:

### 1. JSON Export (.json)
A single JSON file containing all league data with metadata.

### 2. ZIP Export (.zip)
A ZIP archive containing:
- `data.json` - Full data export
- `inserts.sql` - SQL INSERT statements for database restoration

### 3. GKLM Export (.gklm)
GridKing Legacy Migration format - SQL file optimized for import.

---

## JSON Export Structure

```json
{
  "metadata": {
    "format_version": "1.4.0",
    "app_name": "Grid King",
    "app_version": "1.4.0",
    "export_date": "2026-03-05T15:30:00Z",
    "export_type": "full",
    "league_name": "My Racing League",
    "total_records": 1234,
    "checksum": "sha256:abcdef..."
  },
  "data": {
    "users": [...],
    "drivers": [...],
    "teams": [...],
    "seasons": [...],
    "races": [...],
    "race_results": [...],
    "penalties": [...],
    "settings": [...],
    "news": [...],
    "calendar_events": [...]
  }
}
```

---

## Metadata Fields

| Field | Type | Description |
|-------|------|-------------|
| `format_version` | string | Export format version (e.g., "1.4.0") |
| `app_name` | string | Application name |
| `app_version` | string | GridKing version that created the export |
| `export_date` | string | ISO 8601 timestamp |
| `export_type` | string | "full" or "partial" |
| `league_name` | string | Name of the league |
| `total_records` | integer | Total number of records exported |
| `checksum` | string | SHA-256 hash for integrity verification |

---

## Data Tables

### Users
```json
{
  "id": 1,
  "username": "racer1",
  "email": "racer@example.com",
  "created_at": "2024-01-15T10:00:00Z",
  "roles": ["driver", "user"]
}
```

Note: Passwords are NOT exported for security reasons.

### Drivers
```json
{
  "id": 1,
  "user_id": 1,
  "driver_number": 44,
  "team_id": 1,
  "nationality": "GB",
  "created_at": "2024-01-15T10:00:00Z"
}
```

### Teams
```json
{
  "id": 1,
  "name": "Red Bull Racing",
  "short_name": "RBR",
  "color": "#1E41FF",
  "logo_path": "uploads/teams/redbull.png",
  "created_at": "2024-01-10T08:00:00Z"
}
```

### Seasons
```json
{
  "id": 1,
  "name": "2025 Season",
  "start_date": "2025-03-01",
  "end_date": "2025-12-15",
  "is_active": true,
  "points_system": "{\"1\":25,\"2\":18,...}"
}
```

### Races
```json
{
  "id": 1,
  "season_id": 1,
  "name": "Bahrain Grand Prix",
  "track": "Bahrain International Circuit",
  "race_date": "2025-03-02T18:00:00Z",
  "status": "Completed",
  "format": "standard",
  "laps": 57
}
```

### Race Results
```json
{
  "id": 1,
  "race_id": 1,
  "driver_id": 1,
  "position": 1,
  "points": 25,
  "fastest_lap": true,
  "pole_position": true,
  "dnf": false,
  "grid_position": 1,
  "gap_to_leader": "0.000"
}
```

### Penalties
```json
{
  "id": 1,
  "race_id": 1,
  "driver_id": 2,
  "type": "time",
  "value": 5,
  "reason": "Causing a collision",
  "points_deduction": 0,
  "applied_by": 1,
  "created_at": "2025-03-02T20:00:00Z"
}
```

### Settings
```json
{
  "key": "league_name",
  "value": "My Racing League",
  "category": "general"
}
```

---

## Import Compatibility

### GridKing v2.0 (Hosted Platform)
Exports from Legacy 1.3.x and 1.4.x are fully compatible with the hosted platform import system.

### Legacy Versions
- 1.4.x can import from 1.3.x, 1.2.x, 1.1.x, 1.0.x
- Backward compatibility maintained for all 1.x versions

---

## Export Tokens

Export downloads are protected by secure tokens:

- Tokens are 64-character hex strings
- Default validity: 24 hours (configurable)
- Single-use recommended for sensitive exports
- Tokens can be revoked by administrators

---

## Best Practices

1. **Regular Backups**: Create weekly full exports
2. **Verify Integrity**: Check the checksum after download
3. **Secure Storage**: Store exports in encrypted locations
4. **Test Imports**: Use the Migration Sandbox before production migration
5. **Keep Multiple Versions**: Retain at least 3 recent exports

---

## Migration to v2.0

1. Ensure all sandbox tests pass
2. Create a fresh full export
3. Log into your GridKing v2.0 account
4. Navigate to Import > Legacy Migration
5. Upload your export file
6. Review the import preview
7. Confirm migration

For detailed migration instructions, see the v2.0 Migration Guide.

---

## Troubleshooting

### Export Fails
- Check disk space
- Verify database connection
- Review error logs in Debug Panel

### Large Exports
- Consider partial exports for testing
- Use ZIP format for better compression
- Increase PHP memory limit if needed

### Import Issues
- Ensure format_version is compatible
- Verify JSON integrity
- Check for special characters in data

---

## Version History

| Version | Changes |
|---------|---------|
| 1.4.0 | Added checksum, format_version, LTS support markers |
| 1.3.2 | Initial standardized export format |
| 1.3.0 | Added secure export tokens |

---

*This documentation is part of GridKing Legacy 1.4.2 - Final Migration Prep*
