#!/usr/bin/env bash
# Downloads book cover images from Open Library for all books in the fixture.
# Run from the project root or this directory.
# Usage: bash plugins/bookclub/src/DataFixtures/covers/download_covers.sh

COVERS_DIR="$(cd "$(dirname "$0")" && pwd)"

declare -a ISBNS=(
    # Classic / literary (Private Book Club)
    "978-0141439518"
    "978-0451524935"
    "978-0060850524"
    "978-0743273565"
    "978-0061120084"
    "978-0140283334"
    "978-0140449136"
    "978-0679720201"
    "978-0142437209"
    "978-0141439600"
    "978-0143105428"
    "978-0679723165"
    "978-0140449266"
    "978-0140187397"
    "978-0684801223"
    "978-0140283297"
    "978-0679735779"
    "978-0060935467"
    "978-0140449327"
    "978-0140449174"
    "978-0140620627"
    "978-0143039433"
    "978-0679732761"
    "978-0679728023"
    "978-0060929879"
    "978-0143106586"
    "978-0140243727"
    "978-0679734529"
    "978-0060850517"
    "978-0140177398"
    "978-0553382563"
    "978-0547928227"
    "978-0544003415"
    "978-0062315007"
    "978-0316769488"
    "978-0385333481"
    "978-0307474278"
    "978-0060531041"
    "978-0143034902"
    "978-0679745587"
    "978-0307387899"
    "978-0385490818"
    "978-0525478812"
    "978-0307949486"
    "978-0316055437"
    "978-0385504201"
    # Pending approval (classic)
    "978-0439064873"
    "978-0439136365"
    "978-0307588371"
    # Tech / non-fiction (Berlin Tech Meetup)
    "978-0132350884"
    "978-0135957059"
    "978-1449373320"
    "978-0201835953"
    "978-0262510875"
    "978-0735619678"
    "978-0134757599"
    "978-0321125217"
    "978-1680502398"
    "978-1736417911"
)

for isbn in "${ISBNS[@]}"; do
    target="${COVERS_DIR}/${isbn}.jpg"
    if [ -f "$target" ]; then
        echo "SKIP  $isbn (already exists)"
        continue
    fi
    echo -n "GET   $isbn ... "
    curl -sL "https://covers.openlibrary.org/b/isbn/${isbn}-L.jpg" -o "$target"
    size=$(stat -c%s "$target" 2>/dev/null || stat -f%z "$target" 2>/dev/null || echo 0)
    echo "done (${size} bytes)"
done
