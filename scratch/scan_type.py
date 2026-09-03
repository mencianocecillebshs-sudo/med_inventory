import os
import re
import json

workspace_dir = r"c:\xampp\htdocs\med_inventory"
exclude_dirs = {".git", ".gemini", "database", "scratch"}

matches = []

# Regex pattern for type
pattern_type = re.compile(r'\btype\b', re.IGNORECASE)

for root, dirs, files in os.walk(workspace_dir):
    dirs[:] = [d for d in dirs if d not in exclude_dirs]
    for file in files:
        if file.endswith((".php", ".js", ".html")):
            file_path = os.path.join(root, file)
            try:
                with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
                    lines = f.readlines()
            except Exception as e:
                continue
            for i, line in enumerate(lines, 1):
                lower_line = line.lower()
                if 'type' in lower_line:
                    relevant = False
                    # Check context: we care about 'type' in relationship to medicine database field.
                    # Ignore standard <button type=...>, <input type=...>, <link type=...> unless it also refers to medicine type
                    # Keep if it contains standard SQL keywords or PHP array keys
                    if "'type'" in lower_line or '"type"' in lower_line or '`type`' in lower_line or '->type' in lower_line or '.type' in lower_line:
                        # Exclude obvious link type/stylesheet/button type elements
                        if not any(x in lower_line for x in ['type="stylesheet"', "type='stylesheet'", 'type="button"', "type='button'", 'type="text"', "type='text'", 'type="password"', 'type="submit"', 'type="number"', 'type="date"', 'type="hidden"']):
                            relevant = True
                    # Check if line contains medicine indicators + type
                    if any(m in lower_line for m in ['medicine', 'meds', 'stock', 'barcode', 'expiry_date', 'selling_price']) and 'type' in lower_line:
                        if not any(x in lower_line for x in ['type="button"', 'type="text"', 'type="submit"', 'type="number"', 'type="date"', 'type="hidden"']):
                            relevant = True
                    
                    if relevant:
                        matches.append({
                            'file': os.path.relpath(file_path, workspace_dir),
                            'line': i,
                            'content': line.strip()
                        })

output_file = os.path.join(workspace_dir, "scratch", "type_matches.json")
with open(output_file, 'w', encoding='utf-8') as f:
    json.dump(matches, f, indent=2, ensure_ascii=False)

print(f"Scanned files and wrote {len(matches)} relevant occurrences to {output_file}")
