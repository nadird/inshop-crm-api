#!/bin/bash

# Array of dates (replace with your actual dates)
dates=("09/01/2024" "15/01/2024")

# Loop through each date
for date in "${dates[@]}"; do
  # Add the date to README.md
  echo "Date: $date" >> README.md

  # Git add the changes
  git add README.md

  # Git commit with a message containing the date
  git commit -m "report $date"

  # Convert the date from dd/mm/yyyy to the format required for the git commit date
  formatted_date=$(date -jf "%d/%m/%Y" "$date" "+%a %b %d 12:00:00 %Y %z")

  echo $formatted_date
  # Amend the commit with the current loop date
  git commit --amend --date="$formatted_date" --no-edit
  git push
done
