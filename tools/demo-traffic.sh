#!/usr/bin/env bash
# Sends demo requests to the local test site so hit counts and the 404 log have data.
BASE="${1:-http://127.0.0.1:8811}"
get() { curl -s -o /dev/null ${2:+-e "$2"} "$BASE$1"; }
for i in 1 2 3 4 5 6 7 8 9 10 11 12; do get /kontakti; done
for i in 1 2 3 4 5 6 7; do get /old-shop/wheels/alloy-17; done
for i in 1 2 3 4 5; do get /blog/2019/11/winter-tyre-guide/; done
for i in 1 2 3; do get '/tyres/winter?utm_source=newsletter'; done
for i in 1 2; do get /discontinued/rim-cleaner; done
get /about
for i in 1 2 3 4 5 6 7 8 9; do get /tyre-pressure "https://forum.example.net/thread/88"; done
for i in 1 2 3 4; do get /summer-tyres-storage "https://www.example-search.com/"; done
for i in 1 2 3; do get /gift-cards; done
get /wp-config.php.bak
get /.env
get /store-locations "https://maps.example.com/"
get /how-to-store-summer-tyres-2 "https://news.example.org/tyres"
for i in 1 2 3 4 5 6; do get /catalogue/winter-tyres-2023 "https://www.example-search.com/"; done
for i in 1 2 3 4 5; do get /blog/tag/winter "https://forum.example.net/thread/12"; done
for i in 1 2; do get /contact-us "https://partner.example.com/links"; done
get /images/old-logo.png
get /how-to-store-summer-tyres-2 "https://news.example.org/tyres"
get /checkout/step-2
