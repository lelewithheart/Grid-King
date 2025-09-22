"""
Driver Commands for Grid King Discord Bot
"""

import discord
from discord.ext import commands
from discord import app_commands
from typing import Optional

class DriversCog(commands.Cog):
    def __init__(self, bot):
        self.bot = bot
    
    @app_commands.command(name="drivers", description="List all drivers in the league")
    @app_commands.describe(limit="Number of drivers to show (default: 20)")
    async def drivers_list(self, interaction: discord.Interaction, limit: Optional[int] = 20):
        """List all drivers"""
        await interaction.response.defer()
        
        try:
            drivers = await self.bot.api_request('drivers')
            if not drivers:
                await interaction.followup.send("❌ Could not fetch drivers list.")
                return
            
            drivers = drivers[:limit]
            
            embed = discord.Embed(
                title="🏎️ League Drivers",
                color=discord.Color.blue()
            )
            
            drivers_text = ""
            for driver in drivers:
                stats = driver.get('statistics', {})
                points = stats.get('total_points', 0)
                wins = stats.get('wins', 0)
                
                drivers_text += f"**{driver['username']}** #{driver['driver_number']}\n"
                
                if driver.get('team_name'):
                    drivers_text += f"    {driver['team_name']} • "
                else:
                    drivers_text += f"    Independent • "
                
                drivers_text += f"{points} pts • {wins} wins\n"
                drivers_text += f"    Platform: {driver.get('platform', 'Unknown')}\n\n"
            
            embed.description = drivers_text
            embed.set_footer(text=f"Showing {len(drivers)} drivers")
            
            await interaction.followup.send(embed=embed)
            
        except Exception as e:
            await interaction.followup.send(f"❌ Error fetching drivers: {str(e)}")
    
    @app_commands.command(name="finddriver", description="Search for a driver by name or number")
    @app_commands.describe(query="Driver name or number to search for")
    async def find_driver(self, interaction: discord.Interaction, query: str):
        """Search for drivers"""
        await interaction.response.defer()
        
        try:
            drivers = await self.bot.api_request(f'drivers/search?q={query}')
            if not drivers:
                await interaction.followup.send(f"❌ No drivers found matching '{query}'.")
                return
            
            if len(drivers) == 1:
                # Show detailed info for single result
                driver = drivers[0]
                detailed = await self.bot.api_request(f'drivers/{driver["id"]}')
                
                if detailed:
                    embed = await self.create_driver_embed(detailed)
                    await interaction.followup.send(embed=embed)
                else:
                    await interaction.followup.send("❌ Could not fetch driver details.")
            else:
                # Show search results
                embed = discord.Embed(
                    title=f"🔍 Search Results for '{query}'",
                    color=discord.Color.blue()
                )
                
                results_text = ""
                for driver in drivers[:10]:
                    results_text += f"**{driver['username']}** #{driver['driver_number']}\n"
                    if driver.get('team_name'):
                        results_text += f"    {driver['team_name']}\n"
                    results_text += "\n"
                
                embed.description = results_text
                embed.set_footer(text=f"Found {len(drivers)} driver(s)")
                
                await interaction.followup.send(embed=embed)
                
        except Exception as e:
            await interaction.followup.send(f"❌ Error searching drivers: {str(e)}")
    
    @app_commands.command(name="driverid", description="Get driver information by ID")
    @app_commands.describe(driver_id="Driver ID number")
    async def driver_by_id(self, interaction: discord.Interaction, driver_id: int):
        """Get driver by ID"""
        await interaction.response.defer()
        
        try:
            driver = await self.bot.api_request(f'drivers/{driver_id}')
            if not driver:
                await interaction.followup.send(f"❌ Driver with ID {driver_id} not found.")
                return
            
            embed = await self.create_driver_embed(driver)
            await interaction.followup.send(embed=embed)
            
        except Exception as e:
            await interaction.followup.send(f"❌ Error fetching driver: {str(e)}")
    
    async def create_driver_embed(self, driver_data: dict) -> discord.Embed:
        """Create detailed driver embed"""
        stats = driver_data.get('statistics', {})
        
        embed = discord.Embed(
            title=f"🏎️ {driver_data['username']} #{driver_data['driver_number']}",
            color=discord.Color.blue()
        )
        
        # Basic info
        embed.add_field(
            name="Team", 
            value=driver_data.get('team_name', 'Independent'), 
            inline=True
        )
        embed.add_field(
            name="Platform", 
            value=driver_data.get('platform', 'Unknown'), 
            inline=True
        )
        embed.add_field(
            name="Country", 
            value=driver_data.get('country', 'Unknown'), 
            inline=True
        )
        
        # Championship stats
        embed.add_field(
            name="Championship Points", 
            value=f"{stats.get('total_points', 0)} pts", 
            inline=True
        )
        embed.add_field(
            name="Championship Position", 
            value=f"P{stats.get('championship_position', 'N/A')}", 
            inline=True
        )
        embed.add_field(
            name="Races Participated", 
            value=stats.get('races_participated', 0), 
            inline=True
        )
        
        # Performance stats
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
        embed.add_field(
            name="DNFs", 
            value=stats.get('dnfs', 0), 
            inline=True
        )
        embed.add_field(
            name="Average Position", 
            value=f"{stats.get('avg_position', 0):.1f}" if stats.get('avg_position') else "N/A", 
            inline=True
        )
        
        # Recent results
        if driver_data.get('recent_results'):
            recent_text = ""
            for result in driver_data['recent_results'][:5]:
                position = result.get('position', 'DNF')
                points = result.get('points', 0)
                race_name = result.get('race_name', 'Unknown Race')
                
                recent_text += f"**{race_name}**: P{position} ({points} pts)\n"
            
            embed.add_field(
                name="Recent Results", 
                value=recent_text or "No recent results", 
                inline=False
            )
        
        # Bio
        if driver_data.get('bio'):
            embed.description = driver_data['bio']
        
        # Driver's livery image if available
        if driver_data.get('livery_image'):
            embed.set_thumbnail(url=driver_data['livery_image'])
        
        return embed

async def setup(bot):
    await bot.add_cog(DriversCog(bot))
