"""
Statistics Commands for Grid King Discord Bot
"""

import discord
from discord.ext import commands
from discord import app_commands
from typing import Optional, Literal

class StatsCog(commands.Cog):
    def __init__(self, bot):
        self.bot = bot
    
    @app_commands.command(name="stats", description="Show various league statistics")
    @app_commands.describe(
        category="Type of statistics to show",
        limit="Number of results to show (default: 10)"
    )
    async def stats(
        self, 
        interaction: discord.Interaction, 
        category: Literal['wins', 'poles', 'fastest_laps', 'podiums', 'points', 'dnf', 'overview'],
        limit: Optional[int] = 10
    ):
        """Show league statistics"""
        await interaction.response.defer()
        
        try:
            if category == 'overview':
                data = await self.bot.api_request('stats/overview')
                if not data:
                    await interaction.followup.send("❌ Could not fetch overview statistics.")
                    return
                
                embed = discord.Embed(
                    title="📊 League Overview",
                    color=discord.Color.purple()
                )
                
                embed.add_field(
                    name="Total Races", 
                    value=data.get('total_races', 0), 
                    inline=True
                )
                embed.add_field(
                    name="Active Drivers", 
                    value=data.get('total_drivers', 0), 
                    inline=True
                )
                embed.add_field(
                    name="Teams", 
                    value=data.get('total_teams', 0), 
                    inline=True
                )
                embed.add_field(
                    name="Total Results", 
                    value=data.get('total_results', 0), 
                    inline=True
                )
                embed.add_field(
                    name="Points Awarded", 
                    value=data.get('total_points_awarded', 0), 
                    inline=True
                )
                
                if data.get('leading_driver'):
                    leading = data['leading_driver']
                    embed.add_field(
                        name="Championship Leader", 
                        value=f"{leading['username']} ({leading['total_points']} pts)", 
                        inline=False
                    )
                
                await interaction.followup.send(embed=embed)
                return
            
            # Get category-specific statistics
            data = await self.bot.api_request(f'stats/{category}')
            if not data or 'data' not in data:
                await interaction.followup.send(f"❌ Could not fetch {category} statistics.")
                return
            
            results = data['data'][:limit]
            
            # Create embed based on category
            embed = await self.create_stats_embed(category, results)
            await interaction.followup.send(embed=embed)
            
        except Exception as e:
            await interaction.followup.send(f"❌ Error fetching statistics: {str(e)}")
    
    async def create_stats_embed(self, category: str, results: list) -> discord.Embed:
        """Create statistics embed based on category"""
        
        category_info = {
            'wins': {
                'title': '🏆 Most Wins',
                'color': discord.Color.gold(),
                'value_field': 'wins',
                'format': lambda x: f"{x} wins"
            },
            'poles': {
                'title': '🏴 Most Pole Positions',
                'color': discord.Color.blue(),
                'value_field': 'poles',
                'format': lambda x: f"{x} poles"
            },
            'fastest_laps': {
                'title': '⚡ Most Fastest Laps',
                'color': discord.Color.red(),
                'value_field': 'fastest_laps',
                'format': lambda x: f"{x} fastest laps"
            },
            'podiums': {
                'title': '🥇 Most Podiums',
                'color': discord.Color.orange(),
                'value_field': 'podiums',
                'format': lambda x: f"{x} podiums"
            },
            'points': {
                'title': '📊 Most Points',
                'color': discord.Color.green(),
                'value_field': 'total_points',
                'format': lambda x: f"{x} pts"
            },
            'dnf': {
                'title': '❌ Most DNFs',
                'color': discord.Color.dark_red(),
                'value_field': 'dnfs',
                'format': lambda x: f"{x} DNFs"
            }
        }
        
        info = category_info.get(category, {
            'title': f'📊 {category.title()} Statistics',
            'color': discord.Color.blue(),
            'value_field': category,
            'format': lambda x: str(x)
        })
        
        embed = discord.Embed(
            title=info['title'],
            color=info['color']
        )
        
        stats_text = ""
        for i, result in enumerate(results, 1):
            username = result.get('username', 'Unknown')
            driver_number = result.get('driver_number', '')
            team_name = result.get('team_name', '')
            value = result.get(info['value_field'], 0)
            
            # Position indicator
            if i == 1:
                pos_icon = "🥇"
            elif i == 2:
                pos_icon = "🥈"
            elif i == 3:
                pos_icon = "🥉"
            else:
                pos_icon = f"{i}."
            
            stats_text += f"{pos_icon} **{username}**"
            if driver_number:
                stats_text += f" #{driver_number}"
            stats_text += f" - {info['format'](value)}\n"
            
            if team_name:
                stats_text += f"    *{team_name}*\n"
            
            # Add additional context for specific categories
            if category == 'dnf' and result.get('dnf_percentage'):
                stats_text += f"    {result['dnf_percentage']}% DNF rate\n"
            elif category == 'points' and result.get('avg_points_per_race'):
                stats_text += f"    {result['avg_points_per_race']} avg pts/race\n"
            
            stats_text += "\n"
        
        embed.description = stats_text
        embed.set_footer(text=f"Showing top {len(results)} results")
        
        return embed
    
    @app_commands.command(name="leaderboard", description="Show top 10 championship standings")
    async def leaderboard(self, interaction: discord.Interaction):
        """Quick leaderboard command"""
        await interaction.response.defer()
        
        try:
            data = await self.bot.api_request('standings')
            if not data or 'standings' not in data:
                await interaction.followup.send("❌ Could not fetch standings data.")
                return
            
            standings = data['standings'][:10]
            season = data.get('season', {})
            
            embed = discord.Embed(
                title=f"🏆 Top 10 - {season.get('name', 'Current Season')}",
                color=discord.Color.gold()
            )
            
            leaderboard_text = ""
            for i, driver in enumerate(standings, 1):
                points = driver['total_points'] or 0
                
                # Position indicators
                if i == 1:
                    pos_icon = "🥇"
                elif i == 2:
                    pos_icon = "🥈"
                elif i == 3:
                    pos_icon = "🥉"
                else:
                    pos_icon = f"**{i}.**"
                
                leaderboard_text += f"{pos_icon} {driver['username']} - {points} pts\n"
            
            embed.description = leaderboard_text
            
            await interaction.followup.send(embed=embed)
            
        except Exception as e:
            await interaction.followup.send(f"❌ Error fetching leaderboard: {str(e)}")
    
    @app_commands.command(name="compare", description="Compare two drivers' statistics")
    @app_commands.describe(
        driver1="First driver name or number",
        driver2="Second driver name or number"
    )
    async def compare_drivers(self, interaction: discord.Interaction, driver1: str, driver2: str):
        """Compare two drivers"""
        await interaction.response.defer()
        
        try:
            # Search for both drivers
            search1 = await self.bot.api_request(f'drivers/search?q={driver1}')
            search2 = await self.bot.api_request(f'drivers/search?q={driver2}')
            
            if not search1 or not search2:
                await interaction.followup.send("❌ Could not find one or both drivers.")
                return
            
            # Get detailed stats for both
            detailed1 = await self.bot.api_request(f'drivers/{search1[0]["id"]}')
            detailed2 = await self.bot.api_request(f'drivers/{search2[0]["id"]}')
            
            if not detailed1 or not detailed2:
                await interaction.followup.send("❌ Could not fetch driver details.")
                return
            
            embed = discord.Embed(
                title=f"🔄 Driver Comparison",
                color=discord.Color.blue()
            )
            
            # Driver names
            name1 = f"{detailed1['username']} #{detailed1['driver_number']}"
            name2 = f"{detailed2['username']} #{detailed2['driver_number']}"
            
            embed.add_field(name="Driver 1", value=name1, inline=True)
            embed.add_field(name="Driver 2", value=name2, inline=True)
            embed.add_field(name="\u200b", value="\u200b", inline=True)  # Spacer
            
            # Compare statistics
            stats1 = detailed1.get('statistics', {})
            stats2 = detailed2.get('statistics', {})
            
            comparisons = [
                ('Championship Points', 'total_points'),
                ('Wins', 'wins'),
                ('Podiums', 'podiums'),
                ('Poles', 'poles'),
                ('Fastest Laps', 'fastest_laps'),
                ('DNFs', 'dnfs'),
                ('Races', 'races_participated')
            ]
            
            for stat_name, stat_key in comparisons:
                val1 = stats1.get(stat_key, 0)
                val2 = stats2.get(stat_key, 0)
                
                embed.add_field(name=stat_name, value=str(val1), inline=True)
                embed.add_field(name=stat_name, value=str(val2), inline=True)
                
                # Winner indicator
                if val1 > val2:
                    winner = "🥇"
                elif val2 > val1:
                    winner = "🥈"
                else:
                    winner = "🟡"
                
                embed.add_field(name="\u200b", value=winner, inline=True)
            
            await interaction.followup.send(embed=embed)
            
        except Exception as e:
            await interaction.followup.send(f"❌ Error comparing drivers: {str(e)}")

async def setup(bot):
    await bot.add_cog(StatsCog(bot))
