"""
Race Commands for Grid King Discord Bot
"""

import discord
from discord.ext import commands
from discord import app_commands
from datetime import datetime
from typing import Optional

class RacesCog(commands.Cog):
    def __init__(self, bot):
        self.bot = bot
    
    @app_commands.command(name="nextrace", description="Show information about the next upcoming race")
    async def next_race(self, interaction: discord.Interaction):
        """Show next upcoming race"""
        await interaction.response.defer()
        
        try:
            races = await self.bot.api_request('races/upcoming')
            if not races:
                await interaction.followup.send("❌ No upcoming races found.")
                return
            
            race = races[0]  # First upcoming race
            
            embed = discord.Embed(
                title=f"🏁 Next Race: {race['name']}",
                color=discord.Color.orange()
            )
            
            embed.add_field(name="Track", value=race['track'], inline=True)
            embed.add_field(name="Format", value=race['format'], inline=True)
            embed.add_field(name="Laps", value=race['laps'], inline=True)
            
            # Format race date
            race_date = datetime.fromisoformat(race['race_date'].replace('Z', '+00:00'))
            embed.add_field(
                name="Date & Time", 
                value=race_date.strftime('%B %d, %Y at %H:%M UTC'), 
                inline=False
            )
            
            # Time until race
            if race.get('time_until'):
                time_data = race['time_until']
                if time_data['days'] > 0:
                    time_text = f"{time_data['days']}d {time_data['hours']}h {time_data['minutes']}m"
                elif time_data['hours'] > 0:
                    time_text = f"{time_data['hours']}h {time_data['minutes']}m"
                else:
                    time_text = f"{time_data['minutes']}m"
                
                embed.add_field(name="Starts in", value=time_text, inline=True)
            
            embed.timestamp = race_date
            
            await interaction.followup.send(embed=embed)
            
        except Exception as e:
            await interaction.followup.send(f"❌ Error fetching next race: {str(e)}")
    
    @app_commands.command(name="schedule", description="Show upcoming race schedule")
    @app_commands.describe(limit="Number of races to show (default: 5)")
    async def schedule(self, interaction: discord.Interaction, limit: Optional[int] = 5):
        """Show race schedule"""
        await interaction.response.defer()
        
        try:
            races = await self.bot.api_request('races/upcoming')
            if not races:
                await interaction.followup.send("❌ No upcoming races found.")
                return
            
            races = races[:limit]
            
            embed = discord.Embed(
                title="📅 Upcoming Race Schedule",
                color=discord.Color.blue()
            )
            
            schedule_text = ""
            for race in races:
                race_date = datetime.fromisoformat(race['race_date'].replace('Z', '+00:00'))
                date_str = race_date.strftime('%b %d, %H:%M UTC')
                
                schedule_text += f"**{race['name']}**\n"
                schedule_text += f"🏁 {race['track']} • {race['format']}\n"
                schedule_text += f"📅 {date_str}\n\n"
            
            embed.description = schedule_text
            embed.set_footer(text=f"Showing next {len(races)} races")
            
            await interaction.followup.send(embed=embed)
            
        except Exception as e:
            await interaction.followup.send(f"❌ Error fetching schedule: {str(e)}")
    
    @app_commands.command(name="lastrace", description="Show results from the most recent race")
    async def last_race(self, interaction: discord.Interaction):
        """Show last race results"""
        await interaction.response.defer()
        
        try:
            races = await self.bot.api_request('races/recent')
            if not races:
                await interaction.followup.send("❌ No recent races found.")
                return
            
            race = races[0]  # Most recent race
            
            embed = discord.Embed(
                title=f"🏁 {race['name']} Results",
                color=discord.Color.green()
            )
            
            embed.add_field(name="Track", value=race['track'], inline=True)
            embed.add_field(name="Format", value=race['format'], inline=True)
            embed.add_field(name="Laps", value=race['laps'], inline=True)
            
            # Race results
            if race.get('results'):
                results_text = ""
                for i, result in enumerate(race['results'][:10], 1):
                    position = result.get('position', 'DNF')
                    points = result.get('points', 0)
                    
                    # Position emoji
                    if i == 1:
                        pos_icon = "🥇"
                    elif i == 2:
                        pos_icon = "🥈"
                    elif i == 3:
                        pos_icon = "🥉"
                    else:
                        pos_icon = f"{i}."
                    
                    results_text += f"{pos_icon} **{result['username']}** #{result['driver_number']}\n"
                    if result.get('team_name'):
                        results_text += f"    *{result['team_name']}* • {points} pts\n"
                    else:
                        results_text += f"    {points} pts\n"
                    
                    # Special achievements
                    achievements = []
                    if result.get('pole_position'):
                        achievements.append("🏴 Pole")
                    if result.get('fastest_lap'):
                        achievements.append("⚡ Fastest Lap")
                    if result.get('dnf'):
                        achievements.append("❌ DNF")
                    
                    if achievements:
                        results_text += f"    {' • '.join(achievements)}\n"
                    
                    results_text += "\n"
                
                embed.description = results_text
            
            # Race date
            race_date = datetime.fromisoformat(race['race_date'].replace('Z', '+00:00'))
            embed.timestamp = race_date
            
            await interaction.followup.send(embed=embed)
            
        except Exception as e:
            await interaction.followup.send(f"❌ Error fetching race results: {str(e)}")
    
    @app_commands.command(name="raceresults", description="Show results for a specific race")
    @app_commands.describe(race_id="Race ID number")
    async def race_results(self, interaction: discord.Interaction, race_id: int):
        """Show specific race results"""
        await interaction.response.defer()
        
        try:
            race = await self.bot.api_request(f'races/{race_id}')
            if not race:
                await interaction.followup.send(f"❌ Race with ID {race_id} not found.")
                return
            
            embed = discord.Embed(
                title=f"🏁 {race['name']} Results",
                color=discord.Color.green()
            )
            
            embed.add_field(name="Track", value=race['track'], inline=True)
            embed.add_field(name="Format", value=race['format'], inline=True)
            embed.add_field(name="Laps", value=race['laps'], inline=True)
            
            # Race results
            if race.get('results'):
                results_text = ""
                for i, result in enumerate(race['results'][:15], 1):
                    position = result.get('position', 'DNF')
                    points = result.get('points', 0)
                    
                    results_text += f"**P{position}** {result['username']} #{result['driver_number']} - {points} pts\n"
                    
                    if result.get('team_name'):
                        results_text += f"    *{result['team_name']}*\n"
                
                embed.description = results_text
            else:
                embed.description = "No results available for this race."
            
            # Race date
            race_date = datetime.fromisoformat(race['race_date'].replace('Z', '+00:00'))
            embed.timestamp = race_date
            
            await interaction.followup.send(embed=embed)
            
        except Exception as e:
            await interaction.followup.send(f"❌ Error fetching race results: {str(e)}")

async def setup(bot):
    await bot.add_cog(RacesCog(bot))
