import json
import collections

with open("scratch/type_matches.json", "r", encoding="utf-8") as f:
    matches = json.load(f)

by_file = collections.defaultdict(list)
for m in matches:
    by_file[m['file']].append((m['line'], m['content']))

print(f"Total files with potential matches: {len(by_file)}")
for filename, occurrences in sorted(by_file.items()):
    print(f"\nFile: {filename} ({len(occurrences)} occurrences)")
    for line, content in occurrences:
        # safely encode/decode to print in any environment
        safe_content = content.encode('ascii', errors='replace').decode('ascii')
        print(f"  Line {line:4d}: {safe_content}")
