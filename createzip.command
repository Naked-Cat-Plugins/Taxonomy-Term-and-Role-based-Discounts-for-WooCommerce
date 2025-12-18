#! /bin/bash

cd /Users/marcoalmeida/Documents/Websites/_local/wordpress-testing/app/public/wp-content/plugins/

rm -f taxonomy-discounts-woocommerce.zip

# Read .distignore and convert to zip exclusions
echo "Reading exclusions from .distignore..."
EXCLUSIONS=""
while IFS= read -r line || [ -n "$line" ]; do
    # Trim whitespace
    line=$(echo "$line" | xargs)
    # Skip empty lines and comments (lines starting with #)
    if [[ -z "$line" || "$line" == \#* ]]; then
        continue
    fi
    # Add taxonomy-discounts-woocommerce/ prefix and wildcard for directories
    EXCLUSIONS="$EXCLUSIONS -x \"taxonomy-discounts-woocommerce/$line\" -x \"taxonomy-discounts-woocommerce/$line/*\""
done < "taxonomy-discounts-woocommerce/.distignore"

echo "Using exclusions from .distignore"
eval zip -r "taxonomy-discounts-woocommerce.zip" "taxonomy-discounts-woocommerce" $EXCLUSIONS