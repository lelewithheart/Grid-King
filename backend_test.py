#!/usr/bin/env python3
import requests
import sys
import json
from datetime import datetime

class RacingLeagueAPITester:
    def __init__(self, base_url="https://607ad625-0bbb-49f8-9a08-186d4609ed96.preview.emergentagent.com"):
        self.base_url = base_url
        self.api_url = f"{base_url}/api"
        self.session = requests.Session()
        self.tests_run = 0
        self.tests_passed = 0

    def run_test(self, name, method, endpoint, expected_status=200, data=None, check_content=None):
        """Run a single API test"""
        url = f"{self.api_url}/{endpoint}"
        
        self.tests_run += 1
        print(f"\n🔍 Testing {name}...")
        
        try:
            if method == 'GET':
                response = self.session.get(url)
            elif method == 'POST':
                response = self.session.post(url, json=data)
            
            # Check status code
            status_success = response.status_code == expected_status
            
            # Check content if specified
            content_success = True
            if check_content and status_success:
                if isinstance(check_content, str):
                    content_success = check_content in response.text
                elif callable(check_content):
                    content_success = check_content(response.text)
            
            success = status_success and content_success
            
            if success:
                self.tests_passed += 1
                print(f"✅ Passed - Status: {response.status_code}")
                if response.text:
                    try:
                        json_response = response.json()
                        print(f"Response: {json.dumps(json_response, indent=2)[:200]}...")
                    except:
                        print(f"Response: {response.text[:200]}...")
            else:
                if not status_success:
                    print(f"❌ Failed - Expected status {expected_status}, got {response.status_code}")
                if not content_success:
                    print(f"❌ Failed - Content check failed")
                if response.text:
                    print(f"Response: {response.text[:200]}...")
            
            return success, response
        
        except Exception as e:
            print(f"❌ Failed - Error: {str(e)}")
            return False, None

    def test_root_endpoint(self):
        """Test if the root API endpoint works"""
        return self.run_test(
            "Root API Endpoint",
            "GET",
            "",
            expected_status=200,
            check_content="message"
        )

    def test_status_endpoint(self):
        """Test if the status endpoint works"""
        data = {
            "client_name": f"test_client_{datetime.now().strftime('%H%M%S')}"
        }
        return self.run_test(
            "Status Check Endpoint",
            "POST",
            "status",
            expected_status=200,
            data=data
        )

    def test_get_status_checks(self):
        """Test getting status checks"""
        return self.run_test(
            "Get Status Checks",
            "GET",
            "status",
            expected_status=200
        )

    def print_summary(self):
        """Print test summary"""
        print("\n" + "="*50)
        print(f"📊 Tests Summary: {self.tests_passed}/{self.tests_run} passed")
        print("="*50)
        
        if self.tests_passed == self.tests_run:
            print("✅ All tests passed!")
            return 0
        else:
            print(f"❌ {self.tests_run - self.tests_passed} tests failed")
            return 1

def main():
    tester = RacingLeagueAPITester()
    
    # Test API endpoints
    tester.test_root_endpoint()
    tester.test_status_endpoint()
    tester.test_get_status_checks()
    
    # Print summary and return exit code
    return tester.print_summary()

if __name__ == "__main__":
    sys.exit(main())