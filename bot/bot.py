"""
Grid King Discord Bot
Interactive bot for SimRacing League Management

Requirements:
- discord.py >= 2.3.0
- aiohttp >= 3.8.0
- python-dotenv >= 1.0.0
"""

import discord
from discord.ext import commands, tasks
import aiohttp
import asyncio
import json
import os
from datetime import datetime, timedelta
from typing import Optional, List, Dict
import logging

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(name)s - %(levelname)s - %(message)s')
logger = logging.getLogger('gridking_bot')

class GridKingBot(commands.Bot):
    def __init__(self):
        intents = discord.Intents.default()
        intents.message_content = True
        
        super().__init__(
            command_prefix='!',
            intents=intents,
            description='Grid King League Management Bot'
        )
        
        # Configuration
        self.api_base_url = os.getenv('GRIDKING_API_URL', 'http://localhost/api')
        self.api_key = os.getenv('GRIDKING_API_KEY', '')
        self.guild_id = int(os.getenv('DISCORD_GUILD_ID', '0'))
        self.results_channel_id = int(os.getenv('DISCORD_RESULTS_CHANNEL', '0'))
        self.notifications_channel_id = int(os.getenv('DISCORD_NOTIFICATIONS_CHANNEL', '0'))
        
        # HTTP session for API calls
        self.session = None
        
    async def setup_hook(self):
        """Initialize the bot"""
        # Create HTTP session
        self.session = aiohttp.ClientSession(
            headers={
                'Authorization': f'Bearer {self.api_key}',
                'Content-Type': 'application/json'
            }
        )
        
        # Load cogs
        await self.load_extension('commands.standings')
        await self.load_extension('commands.races')
        await self.load_extension('commands.drivers')
        await self.load_extension('commands.stats')
        
        # Start background tasks
        self.check_upcoming_races.start()
        
        logger.info("Bot setup completed")
    
    async def on_ready(self):
        """Bot is ready and connected"""
        logger.info(f'{self.user} has connected to Discord!')
        
        # Sync slash commands
        try:
            guild = discord.Object(id=self.guild_id) if self.guild_id else None
            synced = await self.tree.sync(guild=guild)
            logger.info(f'Synced {len(synced)} command(s)')
        except Exception as e:
            logger.error(f'Failed to sync commands: {e}')
    
    async def close(self):
        """Clean shutdown"""
        if self.session:
            await self.session.close()
        await super().close()
    
    async def api_request(self, endpoint: str, method: str = 'GET') -> Optional[Dict]:
        """Make API request to Grid King"""
        if not self.session:
            return None
            
        url = f"{self.api_base_url}/{endpoint.lstrip('/')}"
        
        try:
            async with self.session.request(method, url) as response:
                if response.status == 200:
                    return await response.json()
                else:
                    logger.error(f'API request failed: {response.status} - {await response.text()}')
                    return None
        except Exception as e:
            logger.error(f'API request error: {e}')
            return None
    
    @tasks.loop(hours=1)
    async def check_upcoming_races(self):
        """Check for upcoming races and send reminders"""
        try:
            races = await self.api_request('races/upcoming')
            if not races:
                return
            
            now = datetime.utcnow()
            
            for race in races[:3]:  # Check next 3 races
                race_date = datetime.fromisoformat(race['race_date'].replace('Z', '+00:00'))
                time_until = race_date - now
                
                # Send reminder 24 hours before race
                if timedelta(hours=23, minutes=30) <= time_until <= timedelta(hours=24, minutes=30):
                    await self.send_race_reminder(race, time_until)
                    
                # Send reminder 1 hour before race  
                elif timedelta(minutes=30) <= time_until <= timedelta(hours=1, minutes=30):
                    await self.send_race_reminder(race, time_until, urgent=True)
                    
        except Exception as e:
            logger.error(f'Error checking upcoming races: {e}')
    
    async def send_race_reminder(self, race: Dict, time_until: timedelta, urgent: bool = False):
        """Send race reminder to notifications channel"""
        if not self.notifications_channel_id:
            return
            
        channel = self.get_channel(self.notifications_channel_id)
        if not channel:
            return
        
        hours = int(time_until.total_seconds() // 3600)
        minutes = int((time_until.total_seconds() % 3600) // 60)
        
        embed = discord.Embed(
            title="🏁 Race Reminder" if not urgent else "🚨 Race Starting Soon!",
            color=discord.Color.orange() if not urgent else discord.Color.red()
        )
        
        embed.add_field(name="Race", value=race['name'], inline=True)
        embed.add_field(name="Track", value=race['track'], inline=True)
        embed.add_field(name="Format", value=race['format'], inline=True)
        
        if hours > 0:
            time_text = f"{hours}h {minutes}m"
        else:
            time_text = f"{minutes}m"
            
        embed.add_field(name="Starts in", value=time_text, inline=False)
        
        embed.timestamp = datetime.fromisoformat(race['race_date'].replace('Z', '+00:00'))
        
        try:
            await channel.send(embed=embed)
        except Exception as e:
            logger.error(f'Failed to send race reminder: {e}')

# Bot instance
bot = GridKingBot()

if __name__ == '__main__':
    # Load environment variables
    from dotenv import load_dotenv
    load_dotenv()
    
    # Run bot
    token = os.getenv('DISCORD_BOT_TOKEN')
    if not token:
        logger.error('DISCORD_BOT_TOKEN not found in environment variables')
        exit(1)
    
    try:
        bot.run(token)
    except Exception as e:
        logger.error(f'Failed to start bot: {e}')
