"""
Standings Commands for Grid King Discord Bot
"""

import discord
from discord.ext import commands
from discord import app_commands
from typing import Optional

class StandingsCog(commands.Cog):
    def __init__(self, bot):
        self.bot = bot
    
    @app_commands.command(name="standings", description="Show current championship standings")
    @app_commands.describe(limit="Number of drivers to show (default: 10)")
    async def standings(self, interaction: discord.Interaction, limit: Optional[int] = 10):
        """Display championship standings"""
        await interaction.response.defer()
        
        try:
            data = await self.bot.api_request('standings')
            if not data or 'standings' not in data:
                await interaction.followup.send("❌ Could not fetch standings data.")
                return
            
            standings = data['standings'][:limit]
            season = data.get('season', {})
            
            embed = discord.Embed(
                title=f"🏆 Championship Standings - {season.get('name', 'Current Season')}",
                color=discord.Color.gold()
            )
            
            standings_text = ""
            for i, driver in enumerate(standings, 1):
                points = driver['total_points'] or 0
                wins = driver['wins'] or 0
                
                # Position indicator
                if i == 1:
                    pos_icon = "🥇"
                elif i == 2:
                    pos_icon = "🥈"
                elif i == 3:
                    pos_icon = "🥉"
                else:
                    pos_icon = f"{i}."
                
                standings_text += f"{pos_icon} **{driver['username']}** #{driver['driver_number']}\n"
                standings_text += f"    {points} pts • {wins} wins\n"
                
                if driver['team_name']:
                    standings_text += f"    *{driver['team_name']}*\n"
                standings_text += "\n"
            
            embed.description = standings_text
            embed.set_footer(text=f"Showing top {len(standings)} drivers")
            
            await interaction.followup.send(embed=embed)
            
        except Exception as e:
            await interaction.followup.send(f"❌ Error fetching standings: {str(e)}")
    
    @app_commands.command(name="driver", description="Show detailed driver information")
    @app_commands.describe(driver="Driver name or number")
    async def driver_info(self, interaction: discord.Interaction, driver: str):
        """Show detailed driver information"""
        await interaction.response.defer()
        
        try:
            # First search for the driver
            search_data = await self.bot.api_request(f'drivers/search?q={driver}')
            if not search_data:
                await interaction.followup.send(f"❌ Driver '{driver}' not found.")
                return
            
            if not search_data:
                await interaction.followup.send(f"❌ No drivers found matching '{driver}'.")
                return
            
            # Get detailed info for first match
            driver_data = search_data[0]
            detailed = await self.bot.api_request(f'drivers/{driver_data["id"]}')
            
            if not detailed:
                await interaction.followup.send("❌ Could not fetch driver details.")
                return
            
            stats = detailed.get('statistics', {})
            
            embed = discord.Embed(
                title=f"🏎️ {detailed['username']} #{detailed['driver_number']}",
                color=discord.Color.blue()
            )
            
            # Basic info
            embed.add_field(
                name="Team", 
                value=detailed.get('team_name', 'Independent'), 
                inline=True
            )
            embed.add_field(
                name="Platform", 
                value=detailed.get('platform', 'Unknown'), 
                inline=True
            )
            embed.add_field(
                name="Country", 
                value=detailed.get('country', 'Unknown'), 
                inline=True
            )
            
            # Statistics
            embed.add_field(
                name="Championship Points", 
                value=f"{stats.get('total_points', 0)} pts", 
                inline=True
            )
            embed.add_field(
                name="Races", 
                value=stats.get('races_participated', 0), 
                inline=True
            )
            embed.add_field(
                name="Wins", 
                value=stats.get('wins', 0), 
                inline=True
            )
            embed.add_field(
                name="Podiums", 
                value=stats.get('podiums', 0), 
                inline=True
            )
            embed.add_field(
                name="Poles", 
                value=stats.get('poles', 0), 
                inline=True
            )
            embed.add_field(
                name="Fastest Laps", 
                value=stats.get('fastest_laps', 0), 
                inline=True
            )
            
            # Recent results
            if detailed.get('recent_results'):
                recent_text = ""
                for result in detailed['recent_results'][:3]:
                    position = result.get('position', 'DNF')
                    points = result.get('points', 0)
                    recent_text += f"**{result['race_name']}**: P{position} ({points} pts)\n"
                
                embed.add_field(
                    name="Recent Results", 
                    value=recent_text or "No recent results", 
                    inline=False
                )
            
            if detailed.get('bio'):
                embed.description = detailed['bio']
            
            await interaction.followup.send(embed=embed)
            
        except Exception as e:
            await interaction.followup.send(f"❌ Error fetching driver info: {str(e)}")
    
    @app_commands.command(name="team", description="Show team information and standings")
    @app_commands.describe(team="Team name")
    async def team_info(self, interaction: discord.Interaction, team: str):
        """Show team information"""
        await interaction.response.defer()
        
        try:
            # Search for team
            search_data = await self.bot.api_request(f'teams/search?q={team}')
            if not search_data:
                await interaction.followup.send(f"❌ Team '{team}' not found.")
                return
            
            # Get detailed info for first match
            team_data = search_data[0]
            detailed = await self.bot.api_request(f'teams/{team_data["id"]}')
            
            if not detailed:
                await interaction.followup.send("❌ Could not fetch team details.")
                return
            
            stats = detailed.get('statistics', {})
            
            embed = discord.Embed(
                title=f"🏁 {detailed['name']}",
                color=discord.Color.green()
            )
            
            # Team statistics
            embed.add_field(
                name="Total Points", 
                value=f"{stats.get('total_points', 0)} pts", 
                inline=True
            )
            embed.add_field(
                name="Wins", 
                value=stats.get('wins', 0), 
                inline=True
            )
            embed.add_field(
                name="Podiums", 
                value=stats.get('podiums', 0), 
                inline=True
            )
            
            # Drivers
            if detailed.get('drivers'):
                drivers_text = ""
                for driver in detailed['drivers']:
                    drivers_text += f"#{driver['driver_number']} {driver['username']}\n"
                
                embed.add_field(
                    name="Drivers", 
                    value=drivers_text, 
                    inline=False
                )
            
            await interaction.followup.send(embed=embed)
            
        except Exception as e:
            await interaction.followup.send(f"❌ Error fetching team info: {str(e)}")

async def setup(bot):
    await bot.add_cog(StandingsCog(bot))
