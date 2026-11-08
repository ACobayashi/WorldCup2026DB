"""FOURWARD coursework | Author: 韩羽宸 | Capture verified DQD goalkeeper match statistics."""
import argparse
import concurrent.futures
import hashlib
import json
import subprocess
import time
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FORMATIONS = ROOT / "data/worldcup/formations.json"
NAMES = ROOT / "data/worldcup/player-names.json"
OUTPUT = ROOT / "data/worldcup/goalkeeper-metrics.json"
CACHE = ROOT / ".test-tmp/goalkeeper-lineups"

FIELD_MAP = {
    "阻止进球": "goals_prevented",
    "扑救": "saves",
    "禁区射门扑救": "saves_inside_box",
    "扑点球": "penalties_saved",
    "扑出点球": "penalties_saved",
    "点球大战扑救": "shootout_saves",
    "摘高球": "high_claims",
    "高空球扑救": "high_claims",
    "拳击球": "punches",
    "门线解围": "goal_line_clearances",
}
PAIR_MAP = {
    "出击/成功": ("sweeper_actions", "sweeper_actions_won"),
    "出击成功/总数": ("sweeper_actions_won", "sweeper_actions"),
}


def fetch(url: str) -> bytes:
    error = None
    for attempt in range(3):
        try:
            completed = subprocess.run(
                ["curl.exe", "-L", "--silent", "--show-error", "--fail", "--max-time", "60", url],
                check=True, capture_output=True,
            )
            return completed.stdout
        except Exception as caught:
            error = caught
            time.sleep(1.5 * (attempt + 1))
    raise RuntimeError(f"Unable to fetch {url}: {error}")


def number(value: str, *, decimal: bool = False):
    value = value.strip().replace("次", "")
    if not value or value == "-":
        return None
    return float(value) if decimal else int(value)


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--workers", type=int, default=8)
    args = parser.parse_args()
    formations = json.loads(FORMATIONS.read_text(encoding="utf-8"))
    identities = json.loads(NAMES.read_text(encoding="utf-8"))["players"]
    fifa_by_dqd = {str(value["dqd_id"]): key for key, value in identities.items() if value.get("dqd_id")}
    rows, sources, observed = [], [], set()

    def load_match(item):
        match_key, match = item
        cache_path = CACHE / (match["source_match_id"] + ".json")
        if cache_path.is_file():
            payload = cache_path.read_bytes()
        else:
            payload = fetch(match["source_url"])
            json.loads(payload)
            cache_path.write_bytes(payload)
        return match_key, match, json.loads(payload), hashlib.sha256(payload).hexdigest()

    items = list(formations["matches"].items())
    CACHE.mkdir(parents=True, exist_ok=True)
    with concurrent.futures.ThreadPoolExecutor(max_workers=max(1, args.workers)) as pool:
      loaded = list(pool.map(load_match, items))
    for match_key, match, response, checksum in loaded:
        sources.append({"match_id": match_key, "source_url": match["source_url"], "sha256": checksum})
        for team_key in ("team_A", "team_B"):
            team = response.get("persons", {}).get(team_key, {})
            for player in (team.get("lineups") or []) + (team.get("sub") or []):
                if player.get("position") != "门将" or not player.get("is_statistic"):
                    continue
                fifa_id = fifa_by_dqd.get(str(player.get("person_id")))
                if fifa_id is None:
                    raise RuntimeError(f"Unmapped DQD goalkeeper {player.get('person_id')} in {match_key}")
                metric_fields = list(dict.fromkeys([*FIELD_MAP.values(), "sweeper_actions", "sweeper_actions_won"]))
                metrics = {key: None for key in metric_fields}
                raw = {}
                for group in player.get("statistics", {}).get("details", []):
                    if group.get("type") != "门将":
                        continue
                    for item in group.get("data", []):
                        label, value = item.get("type", ""), str(item.get("data", "")).strip()
                        raw[label] = value
                        observed.add(label)
                        if label in FIELD_MAP:
                            metrics[FIELD_MAP[label]] = number(value, decimal=label == "阻止进球")
                        elif label in PAIR_MAP:
                            parts = value.split("/")
                            if len(parts) != 2:
                                raise RuntimeError(f"Invalid goalkeeper pair {label}={value}")
                            metrics[PAIR_MAP[label][0]] = number(parts[0])
                            metrics[PAIR_MAP[label][1]] = number(parts[1])
                rows.append({
                    "match_id": match_key,
                    "player_id": f"fifa:player:{fifa_id}",
                    "dqd_player_id": str(player["person_id"]),
                    "player_name": player.get("person"),
                    "team_name": team.get("team_name"),
                    "metrics": metrics,
                    "raw_goalkeeper_statistics": raw,
                })
    unknown = sorted(observed - set(FIELD_MAP) - set(PAIR_MAP))
    if unknown:
        raise RuntimeError("Unmapped goalkeeper fields: " + ", ".join(unknown))
    unique = {(row["match_id"], row["player_id"]) for row in rows}
    if len(unique) != len(rows):
        raise RuntimeError("Duplicate goalkeeper match rows")
    result = {
        "schema": 1,
        "edition": 2026,
        "provider": "懂球帝",
        "captured_at": datetime.now(timezone.utc).isoformat(),
        "fields": list(dict.fromkeys([*FIELD_MAP.values(), "sweeper_actions", "sweeper_actions_won"])),
        "observed_source_fields": sorted(observed),
        "matches_checked": len(sources),
        "rows": rows,
        "sources": sources,
    }
    OUTPUT.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Captured {len(rows)} goalkeeper appearances from {len(sources)} matches; fields: {', '.join(sorted(observed))}")


if __name__ == "__main__":
    main()
