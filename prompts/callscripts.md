Write 3 short demo phone conversations showing an AI receptionist answering calls
for this real business:

Business: {business_name} — {category_name} in {city}, Indiana
Services: {services}
Years in business: {years}
Service area: {service_area}

The receptionist answers as the business. Warm, plain-spoken, midwestern — a
capable front-desk person, not a robot and not a salesperson.

HARD RULES — the receptionist must NEVER:
- state or invent a price, rate, or estimate ("the owner will text you a quote today")
- promise availability or book a time ("I'll have them confirm a time with you")
- claim any service, credential, or fact not listed above
It always captures the caller's name, number, and what they need.

The 3 scenarios, exactly these keys:
1. "quote" — caller asks how much something costs. Label: "Asks for a price"
2. "after_hours" — caller has an urgent problem at 9pm. Label: "Calls at 9pm"
3. "booking" — caller wants someone out this week. Label: "Wants it done this week"

Each conversation: receptionist answers first with a natural greeting naming the
business, then 4–6 total exchanges, under 90 spoken seconds. Caller sounds like a
real Indiana customer with a specific, ordinary problem.

Return ONLY valid JSON — no markdown fences, no explanation:

{"scenarios":[{"scenario":"quote","label":"Asks for a price","lines":[{"speaker":"Receptionist","line":"..."},{"speaker":"Caller","line":"..."}]}]}
