# Grid King Discord Bot

## Overview
The Grid King Discord Bot provides interactive commands for league members to check standings, race results, driver information, and statistics directly from Discord.

## Features

### Slash Commands
- `/standings [limit]` - Show championship standings
- `/driver <name>` - Show detailed driver information
- `/team <name>` - Show team information
- `/nextrace` - Show next upcoming race
- `/schedule [limit]` - Show upcoming race schedule
- `/lastrace` - Show most recent race results
- `/raceresults <race_id>` - Show specific race results
- `/drivers [limit]` - List all drivers
- `/finddriver <query>` - Search for drivers
- `/stats <category> [limit]` - Show various statistics
- `/leaderboard` - Show top 10 championship standings
- `/compare <driver1> <driver2>` - Compare two drivers

### Automatic Features
- Race reminders (24 hours and 1 hour before races)
- Automatic result posting (when configured)
- Integration with Grid King webhook system

## Setup Instructions

### Prerequisites
- Python 3.8 or higher
- Discord Application with Bot Token
- Grid King league with API access

### 1. Create Discord Application
1. Go to https://discord.com/developers/applications
2. Create a new application
3. Go to "Bot" section and create a bot
4. Copy the Bot Token
5. Enable "Message Content Intent" if needed

### 2. Install Dependencies
```bash
cd bot
pip install -r requirements.txt
```

### 3. Configure Environment
1. Copy `.env.example` to `.env`
2. Fill in your configuration values:
   ```
   DISCORD_BOT_TOKEN=your_bot_token_here
   DISCORD_GUILD_ID=your_server_id
   DISCORD_RESULTS_CHANNEL=channel_id_for_results
   DISCORD_NOTIFICATIONS_CHANNEL=channel_id_for_notifications
   GRIDKING_API_URL=http://your-gridking-domain.com/api
   GRIDKING_API_KEY=your_api_key_from_admin_panel
   ```

### 4. Invite Bot to Server
Create an invite link with these permissions:
- Send Messages
- Use Slash Commands
- Embed Links
- Read Message History

Scopes: `bot` + `applications.commands`

### 5. Generate API Key
1. Go to Grid King Admin Panel > Integration Settings
2. Generate an API key for the bot
3. Add the key to your `.env` file

### 6. Run the Bot
```bash
python bot.py
```

## Command Usage

### Basic Commands
- `/standings` - See who's leading the championship
- `/nextrace` - Check when the next race is
- `/driver Max Verstappen` - Get driver details
- `/stats wins` - See who has the most wins

### Advanced Commands
- `/compare "Lewis Hamilton" "Max Verstappen"` - Compare drivers
- `/raceresults 5` - See results from race ID 5
- `/stats overview` - Get league overview statistics

## Configuration

### Channel Setup
Configure specific channels for different types of messages:
- **Results Channel**: Automatic race result posts
- **Notifications Channel**: Race reminders and announcements

### API Permissions
The bot requires these API permissions:
- `standings` - View championship standings
- `races` - View race information and results
- `drivers` - View driver information
- `teams` - View team information

## Troubleshooting

### Common Issues

1. **Bot not responding to commands**
   - Check bot permissions in Discord
   - Verify API key is correct
   - Check bot token is valid

2. **API connection errors**
   - Verify GRIDKING_API_URL is correct
   - Check API key has proper permissions
   - Ensure Grid King server is accessible

3. **Commands not appearing**
   - Bot needs "Use Application Commands" permission
   - May take up to 1 hour for global commands to appear
   - Use guild-specific sync for testing

### Logs
Check console output for detailed error information. The bot logs all API requests and Discord interactions.

## Development

### Adding New Commands
1. Create new command in appropriate cog file
2. Use `@app_commands.command()` decorator
3. Handle errors gracefully
4. Update this README

### API Integration
The bot uses the Grid King REST API with bearer token authentication. All API calls are made through the `bot.api_request()` method.

## Support
For support, check the Grid King documentation or create an issue in the project repository.
