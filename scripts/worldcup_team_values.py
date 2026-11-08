"""FOURWARD coursework | Build verified DQD starting-lineup market-value snapshots."""
import hashlib
import json
import re
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FORMATIONS = ROOT / "data/worldcup/formations.json"
CORE = ROOT / "data/worldcup/full/core.json"
CACHE = ROOT / ".test-tmp/goalkeeper-lineups"
OUTPUT = ROOT / "data/worldcup/team-market-values.json"


def euros(raw: str) -> int:
    match = re.fullmatch(r"([0-9]+(?:\.[0-9]+)?)(万|亿)欧", raw.strip())
    if not match:
        raise RuntimeError(f"Unsupported market-value text: {raw!r}")
    factor = 100_000_000 if match.group(2) == "亿" else 10_000
    return round(float(match.group(1)) * factor)


def main() -> None:
    formations = json.loads(FORMATIONS.read_text(encoding="utf-8"))
    core = json.loads(CORE.read_text(encoding="utf-8"))
    code_by_fifa = {team["id"]: team["code"] for team in core["teams"]}
    snapshots: dict[str, list[dict]] = {}

    for match_id, match in formations["matches"].items():
        cache_path = CACHE / f'{match["source_match_id"]}.json'
        if not cache_path.is_file():
            raise RuntimeError(f"Missing verified DQD cache: {cache_path}")
        payload = cache_path.read_bytes()
        response = json.loads(payload)
        persons = response.get("persons", {})
        for side in ("home", "away"):
            source_side = match["sides"][side]
            starter_ids = {str(player["dqd_id"]) for player in source_side["starters"]}
            candidates = []
            for source_key in ("team_A", "team_B"):
                source_team = persons.get(source_key, {})
                lineup_ids = {str(player["person_id"]) for player in source_team.get("lineups", [])}
                candidates.append((len(starter_ids & lineup_ids), source_team))
            overlap, source_team = max(candidates, key=lambda item: item[0])
            if overlap < 5:
                raise RuntimeError(f"Unable to match DQD lineup side for {match_id}/{side}")
            raw = str(source_team.get("team_market_value", "")).strip()
            fifa_team_id = source_side["team_id"]
            snapshots.setdefault(code_by_fifa[fifa_team_id], []).append({
                "match_id": match_id,
                "match_no": match["match_no"],
                "source_match_id": match["source_match_id"],
                "source_url": match["source_url"],
                "source_sha256": hashlib.sha256(payload).hexdigest(),
                "raw": raw,
                "value_eur": euros(raw),
            })

    teams = {}
    for code, values in sorted(snapshots.items()):
        highest = max(values, key=lambda item: (item["value_eur"], item["match_no"]))
        teams[code] = {
            "highest_starting_xi_value_eur": highest["value_eur"],
            "highest_starting_xi_value_raw": highest["raw"],
            "source_match_id": highest["match_id"],
            "source_match_no": highest["match_no"],
            "source_url": highest["source_url"],
            "source_sha256": highest["source_sha256"],
            "snapshots_checked": len(values),
        }
    if len(teams) != 48 or sum(item["snapshots_checked"] for item in teams.values()) != 208:
        raise RuntimeError("Expected 48 teams and 208 team-match snapshots")
    OUTPUT.write_text(json.dumps({
        "schema": 1,
        "edition": 2026,
        "provider": "懂球帝",
        "captured_at": datetime.now(timezone.utc).isoformat(),
        "definition": "各队在本届赛事逐场首发阵容身价快照中的最高值，不等同于整队注册名单总身价。",
        "matches_checked": 104,
        "teams": teams,
    }, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Wrote {len(teams)} team value records to {OUTPUT}")


if __name__ == "__main__":
    main()
