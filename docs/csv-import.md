# Member roster CSV import

Administrators import an existing roster from **Components → Wendfold → Sync → CSV Upload**.

Column names are configured under **Components → Wendfold → Options → CSV Import**. The mapping is stored in the component parameters. It does not add database columns.

A sample file with fake rows is in [sample-member-import.csv](sample-member-import.csv).

## File rules

- UTF-8 text, with or without a leading BOM
- Comma, semicolon, or tab separators (detected from the file)
- Quoted fields, including separators inside quotes
- Windows (CRLF) or Unix (LF) line endings
- A header row is required. Blank lines are ignored. A short preamble before the header is ignored.
- Maximum size 5 MB
- Maximum 5,000 data rows
- The import matches members who already exist. It does not create accounts. Unknown identifiers are skipped.
- Blank cells do not erase values already stored.
- If any data row fails validation, nothing is written.

Use **Preview import** to see the detected headers, the resolved mapping, and the first sample rows before saving.

## Identifier column

The default header is `Member ID`.

These headers are also accepted. Matching ignores case, spaces, underscores, and hyphens:

- `Member ID`
- `member_id`
- `memberid`
- `Member Number`
- `membernumber`

If Options sets a different identifier header and that column is present, that column is used. The aliases above are still accepted when the configured header is not in the file.

## Default column mapping

| Field | Default header | Also accepted while the default is unchanged |
| --- | --- | --- |
| Member ID | Member ID | member_id, memberid, Member Number, membernumber |
| First Name | First Name | Given Name |
| Last Name | Last Name | Surname, Family Name |
| Email | Email | Email Address |
| Phone | Phone | Phone Number, Telephone, Mobile |
| Address | Address | Street, Street Address, Address 1 |
| City | City | Town |
| State | State | State/Province, Province, Region |
| Postal Code | Postal Code | ZIP, ZIP Code, Postcode |
| Country | Country | |
| Join Date | Join Date | Member Since, Date Joined |
| Expiry Date | Expiry Date | Renewal Date, Expiration Date, EXP |
| Membership Type | Membership Type | Type |
| Life Member | Life Member | |
| Status | Status | Membership Status |
| Chapter | Chapter | Chapter Number, Chapter ID |

Set a custom header in Options when your file uses a different name. A custom header is used only when that exact column (ignoring case and spacing) is in the file. Other fields keep the alias list above only while they are still set to the default header.

## What is saved

For each matched member:

- Expiry date, when the expiry column has a valid date
- Join date, only when the profile does not already have one
- Membership type (`Full` or `1`, `Associate` or `2`, `Life` or `3`). `Life Member` values of Yes, Y, 1, True, or Life force type Life
- Verified flag, from the expiry date when that column is present, otherwise from Status (`Active`, `Current`, and `Good Standing` are verified; `Expired`, `Lapsed`, `Inactive`, `Cancelled`, and `Terminated` are not)
- Chapter on file, and a chapter-switch flag when it differs from the configured chapter number
- Joomla account name, when both first name and last name are present
- Joomla account email, when the address is valid and is not used by another account

Phone, street address, city, state, postal code, and country are validated and shown in the preview. They are not written. Member profiles do not have a general phone column or mailing-address columns.

## Dates and email

Dates must be real calendar dates. `YYYY-MM-DD` is accepted for every file. Comma-separated and tab-delimited files also accept `M/D/YYYY`. Semicolon-separated files also accept `D/M/YYYY` and `D.M.YYYY`. A trailing time of `HH:MM:SS` is ignored. Values such as `32/13/2026` or `not-a-date` fail the row.

Email addresses must be a single valid address with no spaces. An invalid email fails the row.

An invalid phone, membership type, or status also fails the row. The import then reports the row errors and leaves every existing record unchanged.
