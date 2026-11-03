# Player portraits

Sources: native headshots from Dongqiudi 2026 season (26123) match lineup `logo` fields, hosted on its Qunliao CDN; remaining full-body photos from FIFA 2026 season squad responses (`PlayerPicture`), hosted on FIFA Digital Hub. Native headshots are supplied by the source and are not cropped from full-body images.
Photo identities and source URLs: `data/worldcup/player-photos/catalog.json`.
Local download results and checksums: `data/worldcup/player-photos/availability.json`.

Images remain subject to their respective rights holders. Availability via an API is not a grant of redistribution rights. These images are not covered by the CC BY license of statistical CSVs or this repository's code license. Cached photos are excluded from Git; public redistribution requires confirming the applicable image rights separately.

Rebuild the local cache with `python scripts/worldcup_photos.py`. This is a bounded, resumable download of listed assets. Identity verification and source evidence: `data/worldcup/player-photos/headshots.json` and `docs/worldcup-player-photos.md`. No AI-generated substitute photos are used.
