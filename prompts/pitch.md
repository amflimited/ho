Write a cold outreach email FROM Adam Ferree of Hoosier Online TO the owner of {business_name}.

BUSINESS INTELLIGENCE:
{business_name} | {category} | {city}, Indiana
Owner first name: {owner_first_name}
Google: {review_count} reviews at {rating} stars
Standout review: "{quote}" — {quote_author}
Nearest competitor: {competitor_name} ({competitor_rating} stars, {competitor_review_count} reviews)
Years in business: {years}
Current website quality: {website_quality}
Offer: {offer}
Include this URL exactly once: {preview_url}

RULES:
- Body: 80-110 words (not counting greeting or sign-off)
- First sentence must reference something SPECIFIC from the intelligence above
- Zero cliches: no "I noticed", "I came across", "I hope this finds you", "I wanted to reach out"
- Be direct, warm, specific — think knowledgeable neighbour, not marketer
- Exactly ONE URL in the email
- Greeting: "Hi {owner_first_name}," or "Hi,"
- End exactly with: "— Adam\nHoosier Online\nadam@hoosieronline.com"
- Plain text only — no markdown, no bullets, no asterisks

Return ONLY this JSON, nothing else:
{"subject": "...", "body": "..."}
