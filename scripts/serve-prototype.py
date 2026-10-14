"""Serve only the static demo, with a deterministic JavaScript module MIME type."""
import argparse
from functools import partial
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlsplit


class PrototypeHandler(SimpleHTTPRequestHandler):
    # Windows registry associations can otherwise make .mjs text/plain.
    extensions_map = {**SimpleHTTPRequestHandler.extensions_map, ".mjs": "text/javascript"}

    def do_GET(self):
        if urlsplit(self.path).path.lower().endswith(".php"):
            self.send_error(404, "PHP needs the database-backed server")
            return
        super().do_GET()


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--port", type=int, default=5173)
    args = parser.parse_args()
    public = Path(__file__).resolve().parent.parent / "public"
    server = ThreadingHTTPServer(("127.0.0.1", args.port), partial(PrototypeHandler, directory=str(public)))
    print(f"Static demo: http://127.0.0.1:{args.port}/", flush=True)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()
