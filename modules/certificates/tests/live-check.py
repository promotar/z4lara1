"""Local-only concurrent HTTP check; excluded from the distributable ZIP."""
from collections import Counter
from concurrent.futures import ThreadPoolExecutor
import json
import urllib.error
import urllib.request

URL = "http://localhost:8092/999999999999999999"


def attempt(_):
    try:
        response = urllib.request.urlopen(URL, timeout=20)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        body = response.read().decode("utf-8")
        assert response.headers.get("X-Robots-Tag") == "noindex, nofollow, noarchive"
        assert "ناصر حسين" not in body
        if response.status == 429:
            assert int(response.headers["Retry-After"]) > 0
        return response.status


with ThreadPoolExecutor(max_workers=6) as executor:
    counts = Counter(executor.map(attempt, range(35)))
assert counts == {404: 30, 429: 5}, counts
print(json.dumps({"concurrent_requests": 35, "workers": 6, "statuses": dict(counts), "passed": True}))
