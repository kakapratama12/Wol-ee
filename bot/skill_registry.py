"""Static Wol-ee bot skill registry.

Single source for planner prompt and read-only platform display.
"""

from __future__ import annotations

import json
from functools import lru_cache
from pathlib import Path
from typing import Any

SKILLS_PATH = Path(__file__).with_name("skills.json")

INTENT_TO_SKILL = {
    "sale": "record_sale",
    "purchase": "record_purchase",
    "expense": "record_expense",
    "stock": "check_stock",
    "record_sale": "record_sale",
    "record_purchase": "record_purchase",
    "record_expense": "record_expense",
    "check_stock": "check_stock",
}


@lru_cache(maxsize=1)
def skills() -> list[dict[str, Any]]:
    return json.loads(SKILLS_PATH.read_text())


def skill_by_name(name: str) -> dict[str, Any] | None:
    for skill in skills():
        if skill.get("name") == name:
            return skill
    return None


def skill_for_intent(intent: str | None) -> dict[str, Any] | None:
    if not intent:
        return None
    return skill_by_name(INTENT_TO_SKILL.get(intent, intent))


def required_slots_for_intent(intent: str | None) -> list[str]:
    skill = skill_for_intent(intent)
    return list(skill.get("required_slots", [])) if skill else []


def action_skills_prompt() -> str:
    lines: list[str] = []
    for skill in skills():
        if not skill.get("planner_enabled") or skill.get("status") != "active":
            continue
        required = ", ".join(skill.get("required_slots", [])) or "-"
        optional = ", ".join(skill.get("optional_slots", [])) or "-"
        examples = "; ".join(skill.get("examples", [])[:3])
        lines.append(
            f"- {skill['name']}: {skill['description']}\n"
            f"  required_slots: {required}\n"
            f"  optional_slots: {optional}\n"
            f"  tool: {skill['tool']}\n"
            f"  confirmation_required: {str(skill.get('confirmation_required', False)).lower()}\n"
            f"  examples: {examples}"
        )
    return "\n".join(lines)
