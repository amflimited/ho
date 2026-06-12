Find up to {count} REAL, VERIFIABLE {category_name} businesses in the {area} region
of Indiana, and fully research each one in the same pass.

VERIFICATION REQUIREMENTS — these matter more than the count:
- Only include a business you can actually verify exists right now
- Every business MUST have at least one real contact path
- NEVER guess or construct a website URL
- It is COMPLETELY FINE to return fewer than {count}

For each business found, check every public source: their website, Google Business
Profile, Facebook, Instagram, Yelp, Angi, Thumbtack, YouTube, Nextdoor, and BBB.

CRITICAL RULES:
- Review quotes MUST be VERBATIM — max 40 words, no paraphrasing, no leading ellipses
- website_confidence: high (official source), medium (search result), low (guessed)
- website_quality: none/poor/basic/decent ONLY
- All website sub-fields: null when has_website is false
- opportunity_summary: 1-2 sentences to the owner using you/your; specific gap;
  do NOT state the review count as a number

Already in my database — do NOT include these businesses:
{exclusion_list}

Return ONLY valid JSON — no markdown fences, no explanations:

{research_spec}
