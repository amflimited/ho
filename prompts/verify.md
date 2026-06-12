Fact-check these claims about the business "{name}" ({category}) in {city}, Indiana.
Search the web independently — Google Maps/reviews, their website, Facebook.
Be SKEPTICAL: your job is to catch errors before they are sent to the business owner,
who knows the truth. For quotes, the text must appear VERBATIM in a real review;
paraphrases fail. Counts within 15% pass; report the value you actually found.

CLAIMS:
- review_count: "{name}" has {review_count} Google reviews
- rating: their Google rating is {rating}
- quote_1: a real Google review contains "{quote_1}" [VERBATIM]
- quote_2: a real Google review contains "{quote_2}" [VERBATIM]
- competitor: "{competitor_name}" is a real {category} in {city}
- website: this business {website_claim}

Reply with ONLY this JSON (no fences, no commentary):
{
  "checks": {
    "review_count": {"status": "confirmed|wrong|unverifiable", "found": 0},
    "rating":       {"status": "confirmed|wrong|unverifiable", "found": 0.0},
    "quote_1":      {"status": "confirmed|wrong|unverifiable"},
    "quote_2":      {"status": "confirmed|wrong|unverifiable"},
    "competitor":   {"status": "confirmed|wrong|unverifiable", "found_rating": 0.0},
    "website":      {"status": "confirmed|wrong|unverifiable", "found_url": "", "quality": "none|poor|basic|decent"}
  }
}
