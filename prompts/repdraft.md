Find the Google reviews for "{business_name}" ({category}) in {city}, Indiana.

List up to 12 reviews that have NO owner response, prioritising:
lowest ratings first, then most recent, then most detailed.

STRICT RULES: only include reviews you can actually see — text VERBATIM,
never invented, never paraphrased. If you cannot verify the business's reviews,
return an empty list. Fewer real reviews beats more guessed ones.

For each, draft the reply the owner should post. Reply style: warm, specific
to what the reviewer said, plain Indiana voice, no corporate filler, under 75 words.
Thank by first name, reference one concrete detail from their review, invite them back.
For 1-3 star reviews: acknowledge directly, no excuses, no arguing, offer to make
it right with a direct contact, stay calm and classy.

Reply with ONLY this JSON (no fences):
{
  "google_rating": 0.0,
  "google_review_count": 0,
  "reviews": [
    {"author": "", "rating": 5, "date": "YYYY-MM", "text": "", "reply": ""}
  ]
}
