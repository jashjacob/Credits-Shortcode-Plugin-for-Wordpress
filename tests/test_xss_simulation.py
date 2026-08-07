import urllib.parse
import html
import re

def mock_esc_url(url):
    """Simulate WordPress esc_url() sanitization."""
    if not url or url.strip().startswith("javascript:"):
        return ""
    # esc_url replaces quotes with entities/percent encoding to prevent attribute breakout
    url = url.replace('"', '%22').replace("'", '%27')
    clean = re.sub(r'[\r\n\t]', '', url)
    return clean

def mock_esc_html(text):
    """Simulate WordPress esc_html() sanitization."""
    return html.escape(str(text), quote=True)

def mock_credits_print(atts, content=None):
    raw_name = content if content else atts.get("name", "")
    if not raw_name:
        raw_name = "Credit Link"
    
    clean_link = mock_esc_url(atts.get("link", "#"))
    clean_name = mock_esc_html(raw_name)
    category = "Via" if str(atts.get("type", "")).lower() == "via" else "Source"
    
    return f'<ul class="credits"><li class="credits"><span class="cre_cate">{category}</span><span class="cre_cate_link"><a href="{clean_link}" target="_blank" rel="noopener noreferrer">{clean_name}</a></span></li></ul>'

def test_xss_scenarios():
    print("--- Running XSS Payload Tests ---")
    
    # Test Case 1: Malicious Script Tag in Name
    res1 = mock_credits_print({"link": "https://example.com", "type": "source"}, "<script>alert('xss')</script>")
    assert "<script>" not in res1, "Script tag must be escaped"
    assert "&lt;script&gt;" in res1, "Script tag was properly HTML-encoded"
    print("✅ Test 1 (Script Tag in Name): PASSED")
    
    # Test Case 2: Javascript URI in Link
    res2 = mock_credits_print({"link": "javascript:alert('xss')", "type": "via", "name": "Malicious Link"})
    assert "href=\"javascript:" not in res2, "javascript: protocol must be stripped"
    print("✅ Test 2 (javascript: Protocol in URL): PASSED")
    
    # Test Case 3: Attribute Breakout Injection
    res3 = mock_credits_print({"link": 'https://example.com" onclick="alert(1)', "type": "source", "name": "Test"})
    # Verify the double quote was encoded to %22 so it cannot break out of href="..."
    assert 'href="https://example.com%22' in res3, "Quotes inside href must be percent-encoded"
    print("✅ Test 3 (Attribute Breakout Injection): PASSED")

if __name__ == "__main__":
    test_xss_scenarios()
