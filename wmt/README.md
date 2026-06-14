# AP Services Coverage Tool

Deployed page: `/wmt/index.php`

This is the PHP/MySQL starter version of the AP Services coverage and assignment system.

## First run
1. Visit `https://hoosieronline.com/wmt/index.php`.
2. Create the first admin password.
3. Use **Import** to paste JSON or CSV schedule data.
4. Use **Week** to review the full week.
5. Print the daily pages:
   - Management Daily Coverage Review
   - Associate Position Sheets
   - Daily Turn-In / Sign-Off

## Rules coded in
- Services owns doors.
- Short-term target is Grocery + GM coverage from 8AM-5PM.
- Grocery gold standard: 6AM-11PM.
- GM gold standard: 6AM-9PM.
- AP Ops flex only when two Ops associates are available.
- AP Ops flex blackout: 10AM-12PM.
- AP Team Lead flex cap: 15 minutes per day.
- AP Investigator never flexes.
- Tasks happen only after coverage and breaks/lunches are protected.
- Impossible gaps stay visible instead of being forced.

## JSON import format
```json
{
  "replace_schedule_dates": ["2026-06-15"],
  "associates": [
    {"name":"Example TA","team":"Services","role_type":"AP Service TA","preferred_door":"Grocery"}
  ],
  "schedule": [
    {"date":"2026-06-15","name":"Example TA","team":"Services","role_type":"AP Service TA","start":"08:00","end":"17:00","preferred_door":"Grocery"}
  ],
  "tasks": []
}
```

## CSV schedule format
```csv
date,name,team,role_type,start,end,preferred_door,notes
2026-06-15,Example TA,Services,AP Service TA,08:00,17:00,Grocery,
```

## Tables created
- `wmt_settings`
- `wmt_associates`
- `wmt_shifts`
- `wmt_tasks`
- `wmt_turnins`
- `wmt_exceptions`

Use `/wmt/index.php?export=json` to export the current data snapshot after logging in.
