import json
import collections

with open("scratch/type_matches.json", "r", encoding="utf-8") as f:
    matches = json.load(f)

by_file = collections.defaultdict(list)
for m in matches:
    by_file[m['file']].append((m['line'], m['content']))

print(f"Total files: {len(by_file)}")
for filename, occurrences in sorted(by_file.items()):
    print(f"\nFile: {filename} ({len(occurrences)} occurrences)")
    for line, content in occurrences[:15]:
        print(f"  Line {line:4d}: {content}")
    if len(occurrences) > 15:
        print(f"  ... and {len(occurrences) - 15} more")
