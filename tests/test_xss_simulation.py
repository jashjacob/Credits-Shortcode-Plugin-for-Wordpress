import html
import re


def mock_esc_url_raw(url):
    """Simulate WordPress esc_url_raw() sanitization (input)."""
    if not url:
        return ""
    if str(url).strip().lower().startswith("javascript:"):
        return ""
    return re.sub(r"[\r\n\t]", "", str(url))


def mock_esc_url(url):
    """Simulate WordPress esc_url() escaping (href output)."""
    if not url or str(url).strip().lower().startswith("javascript:"):
        return ""
    url = str(url).replace('"', "%22").replace("'", "%27")
    return re.sub(r"[\r\n\t]", "", url)


def mock_sanitize_text_field(text):
    """Simulate WordPress sanitize_text_field() (strips tags, keeps text)."""
    cleaned = re.sub(r"<[^>]*>", "", str(text))
    return cleaned.strip()


def mock_sanitize_hex_color(color):
    """Simulate WordPress sanitize_hex_color()."""
    if not color:
        return ""
    if re.match(r"^#(?:[A-Fa-f0-9]{3}){1,2}$", str(color)):
        return color
    return ""


def mock_esc_html(text):
    """Simulate WordPress esc_html()."""
    return html.escape(str(text), quote=True)


def mock_esc_attr(text):
    """Simulate WordPress esc_attr()."""
    return html.escape(str(text), quote=True)


def mock_credits_print(atts, content=None):
    if content and str(content).strip():
        name = mock_sanitize_text_field(content)
    else:
        name = mock_sanitize_text_field(atts.get("name", ""))
    if not name:
        name = "Credit Link"

    link = mock_esc_url_raw(atts.get("link", "#"))
    if not link:
        link = "#"

    category = "Via" if str(atts.get("type", "")).lower() == "via" else "Source"

    raw_badge = atts.get("badgeColor") or atts.get("badge_color") or ""
    badge_bg = mock_sanitize_hex_color(raw_badge)

    style = ""
    if badge_bg:
        style = ' style="' + mock_esc_attr("background-color: " + badge_bg) + '"'

    return (
        '<ul class="credits"><li class="credits">'
        f'<span class="cre_cate"{style}>{mock_esc_html(category)}</span>'
        f'<span class="cre_cate_link"><a href="{mock_esc_url(link)}" '
        f'target="_blank" rel="noopener noreferrer">{mock_esc_html(name)}</a></span>'
        "</li></ul>"
    )


def test_xss_scenarios():
    print("--- Running XSS Payload Tests ---")

    res1 = mock_credits_print(
        {"link": "https://example.com", "type": "source"},
        "<script>alert('xss')</script>",
    )
    assert "<script>" not in res1, "Script tag must not appear raw"
    print("✅ Test 1 (Script Tag in Name): PASSED")

    res2 = mock_credits_print(
        {"link": "javascript:alert('xss')", "type": "via", "name": "Malicious Link"}
    )
    assert "href=\"javascript:" not in res2, "javascript: protocol must be stripped"
    print("✅ Test 2 (javascript: Protocol in URL): PASSED")

    res3 = mock_credits_print(
        {"link": 'https://example.com" onclick="alert(1)', "type": "source", "name": "Test"}
    )
    assert 'href="https://example.com%22' in res3, "Quotes inside href must be percent-encoded"
    print("✅ Test 3 (Attribute Breakout Injection): PASSED")

    res4 = mock_credits_print(
        {
            "link": "https://example.com",
            "type": "source",
            "name": "Test",
            "badge_color": "red; background-image: url(javascript:alert(1))",
        }
    )
    assert "javascript:" not in res4, "CSS injection in color must be dropped"
    assert "background-image" not in res4, "Non-hex color CSS must be dropped"
    print("✅ Test 4 (CSS injection via color): PASSED")


if __name__ == "__main__":
    test_xss_scenarios()
