import re

input_path = "database/med_inventory-purchases.sql"

with open(input_path, 'r', encoding='utf-8', errors='ignore') as f:
    content = f.read()

# 1. Update medicines table schema definition
table_old = "`type` varchar(50) DEFAULT NULL,"
table_new = "`category` varchar(50) DEFAULT NULL,\n  `item_type` enum('medicine','non-medicine') NOT NULL DEFAULT 'medicine',"
content = content.replace(table_old, table_new, 1)

# 2. Update stand-in view table definition
standin_old = ",`type` varchar(50)\n,`description` text"
standin_new = ",`category` varchar(50)\n,`item_type` enum('medicine','non-medicine')\n,`description` text"
content = content.replace(standin_old, standin_new, 1)

# 3. Update view definition at the end
view_old = "`m`.`type` AS `type`"
view_new = "`m`.`category` AS `category`,`m`.`item_type` AS `item_type`"
content = content.replace(view_old, view_new, 1)

# 4. Parse the INSERT INTO `medicines` statements and add the default item_type 'medicine'
# We search for the INSERT INTO `medicines` statement and rewrite its VALUES
insert_pattern = re.compile(
    r'(INSERT INTO `medicines` \([^)]*`type`[^)]*\) VALUES\s*\n)(.*?)(;\s*\n)',
    re.DOTALL
)

def update_insert_match(match):
    header = match.group(1)
    values_block = match.group(2)
    footer = match.group(3)
    
    # Update header: change `type` to `category`, `item_type`
    header_updated = header.replace("`type`", "`category`, `item_type`")
    
    # Process each row
    rows = []
    # Split rows by separator: '), \n(' or similar
    # A single row pattern: (id, name, barcode, quantity, type, description, ...)
    # Let's match each row and insert 'medicine' after the category field
    # Since parsing SQL tuples with regex is tricky due to quotes/commas, we can do it by parsing strings or using a simple state machine.
    
    # We can match everything between '(' and ')'
    # Since none of the descriptions contain nested parentheses, we can split by '),\n('
    lines = values_block.strip().split('\n')
    updated_lines = []
    for line in lines:
        is_last = line.endswith(')')
        # strip trailing comma if present
        line_clean = line.rstrip(',')
        
        # parse the tuple: we want to find the 5th comma (after the category string value)
        # e.g.: (301, 'Amoxicillin 500mg', '4800000000301', 200, 'Antibiotic', 'Broad-spectrum...'
        # Let's use a quick character scanner to split the tuple fields safely
        fields = []
        current = []
        in_quotes = False
        quote_char = None
        escape = False
        
        # strip the outer parentheses
        tuple_content = line_clean.strip()[1:-1]
        
        for char in tuple_content:
            if escape:
                current.append(char)
                escape = False
            elif char == '\\':
                current.append(char)
                escape = True
            elif char in ("'", '"'):
                current.append(char)
                if in_quotes:
                    if char == quote_char:
                        in_quotes = False
                else:
                    in_quotes = True
                    quote_char = char
            elif char == ',' and not in_quotes:
                fields.append(''.join(current).strip())
                current = []
            else:
                current.append(char)
        fields.append(''.join(current).strip())
        
        # fields[4] is the category. Insert 'medicine' (with quotes) as fields[5]
        fields.insert(5, "'medicine'")
        
        new_line = "  (" + ", ".join(fields) + ")"
        if not is_last:
            new_line += ","
        updated_lines.append(new_line)
        
    return header_updated + '\n'.join(updated_lines) + '\n' + footer

content_updated = insert_pattern.sub(update_insert_match, content)

with open(input_path, 'w', encoding='utf-8') as f:
    f.write(content_updated)

print("Dump file med_inventory-purchases.sql updated successfully!")
