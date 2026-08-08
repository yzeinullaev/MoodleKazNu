# P0 — Faculty ID discovery notes

**Date:** 2026-08-06  
**Status:** Incomplete without live Univer sample payloads  
**Production:** not queried / not changed  

## Endpoints checked (code + client)

| Endpoint | Used in | Faculty today |
|----------|---------|---------------|
| `GET /user/login` | login | userType, userId only |
| `GET /student/GetStudentInfo?culture=` | profile | **candidate** keys: `facultyId`, `FacultyId`, `faculty_id`, `facultyID`, nested `faculty.id` |
| `GET /student/profile/{culture}` | label pairs | name string under key `faculty` only; id only if pair key is `facultyId` |

## Real numeric field found

**Not confirmed on a live BS student payload.**

Parser is ready to accept `facultyId` (int or numeric string `"28"`).  
Display name strings are **never** used as the allow/deny criterion.

## JSON path (implementation candidates, unconfirmed)

1. `data.facultyId` (preferred if present)  
2. `data.FacultyId` / `data.faculty_id` / `data.facultyID`  
3. `data.faculty.id` (if faculty is object)  
4. profile pairs entry with key `facultyId`  

## Validated accounts

None in this implement turn (production live login not authorized).

## Fallback if absent

- `univer_faculty_filter_enabled` remains **0** (default).  
- Filter **must not** be turned on until path confirmed + multi-student stability check.  
- Allowlist alone must **not** become the production access model.  
- Ask Univer API owner for faculty identifier field if still missing.  
- Do **not** enable site-wide fail-closed for all users while filter is OFF.

## CLI for future dry discovery (manual)

Use a controlled student session and dump **key names only** (no PII values) from GetStudentInfo before enabling filter.
