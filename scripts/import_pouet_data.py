#!/usr/bin/env python3
"""Import sample data from data.pouet.net into the pouet MySQL database."""
import argparse
import gzip
import json
import lzma
import os
import random
import sys
import urllib.request

try:
    import pymysql
except ImportError:
    print("pymysql not installed; pip install pymysql", file=sys.stderr)
    sys.exit(1)


def fetch_json(url):
    print(f"  downloading {url}")
    with urllib.request.urlopen(url) as r:
        raw = r.read()
    if url.endswith(".gz"):
        raw = gzip.decompress(raw)
    elif url.endswith(".xz"):
        raw = lzma.decompress(raw)
    return json.loads(raw)


def latest_dump_urls():
    feed = fetch_json("https://data.pouet.net/json.php")
    dumps = feed["dumps"]
    latest_key = sorted(dumps.keys(), reverse=True)[0]
    print(f"Latest dump: {latest_key}")
    return dumps[latest_key]


def conn(args):
    return pymysql.connect(
        host=args.mysql_host, user=args.mysql_user, password=args.mysql_password,
        database=args.mysql_db, charset="utf8mb4", autocommit=False,
    )


def upsert(cur, table, cols, rows, pk="id"):
    if not rows:
        return
    placeholders = ",".join(["%s"] * len(cols))
    collist = ",".join(f"`{c}`" for c in cols)
    # use INSERT ... ON DUPLICATE KEY UPDATE for all non-pk cols
    updates = ",".join(f"`{c}`=VALUES(`{c}`)" for c in cols if c != pk)
    sql = f"INSERT INTO `{table}` ({collist}) VALUES ({placeholders}) ON DUPLICATE KEY UPDATE {updates}"
    cur.executemany(sql, rows)


import datetime as _dt

def to_date(v):
    if not v:
        return None
    if v in ("0000-00-00", "0000-00-00 00:00:00"):
        return None
    s = str(v).strip()
    # try common formats
    for fmt in ("%Y-%m-%d %H:%M:%S", "%Y-%m-%d", "%Y-%m-%d %H:%M"):
        try:
            d = _dt.datetime.strptime(s, fmt)
            if d.month == 0 or d.day == 0:
                return None
            return s
        except ValueError:
            continue
    # fallback: try date part
    try:
        part = s.split()[0]
        _dt.datetime.strptime(part, "%Y-%m-%d")
        y, m, d = part.split("-")
        if int(m) == 0 or int(d) == 0:
            return None
        return part
    except ValueError:
        return None


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--mysql-host", default="mysql")
    ap.add_argument("--mysql-user", default="pouet")
    ap.add_argument("--mysql-password", default="pouet")
    ap.add_argument("--mysql-db", default="pouet")
    ap.add_argument("--www-root", default="/var/www/pouet")
    args = ap.parse_args()

    urls = latest_dump_urls()
    prods_data = fetch_json(urls["prods"]["url"])
    groups_data = fetch_json(urls["groups"]["url"])
    parties_data = fetch_json(urls["parties"]["url"])
    boards_data = fetch_json(urls["boards"]["url"])

    db = conn(args)
    cur = db.cursor()

    # disable fk checks during bulk import
    cur.execute("SET FOREIGN_KEY_CHECKS=0")

    # --- users (collect from prods.addedUser, groups.addedUser, parties.addedUser, boards none) ---
    users = {}
    def add_user(u):
        if not u:
            return
        uid = int(u["id"]) if isinstance(u, dict) else int(u)
        if uid in users:
            return
        if isinstance(u, dict):
            users[uid] = u
        else:
            users[uid] = {"id": str(uid), "nickname": f"user{uid}", "level": "user",
                          "avatar": "", "registerDate": None, "glops": 0, "thumbups": 0,
                          "thumbdowns": 0, "permissionOpenBBS": 1, "ojuice": 0,
                          "slengpung": 0, "csdb": 0, "zxdemo": 0, "demozoo": 0}
    for p in prods_data["prods"]:
        add_user(p.get("addedUser"))
    for g in groups_data["groups"]:
        add_user({"id": g["addedUser"], "nickname": None})
    for pt in parties_data["parties"]:
        add_user({"id": pt["addedUser"], "nickname": None})

    # ensure user 1 exists
    if 1 not in users:
        users[1] = {"id": "1", "nickname": "analogue", "level": "administrator",
                    "avatar": "bobble.gif", "registerDate": "2000-06-25 17:59:58",
                    "glops": 845, "thumbups": 211, "thumbdowns": 39,
                    "permissionOpenBBS": 1, "ojuice": 0, "slengpung": 0,
                    "csdb": 0, "zxdemo": 0, "demozoo": 0}

    user_rows = []
    for uid, u in users.items():
        if not isinstance(u, dict) or "id" not in u:
            u = {"id": str(uid)}
        nickname = u.get("nickname") or f"user{uid}"
        # nickname max 16 chars
        nickname = nickname[:16]
        user_rows.append((
            int(u.get("id", uid)),
            nickname,
            u.get("email"),
            u.get("level") or "user",
            u.get("avatar") or "",
            to_date(u.get("registerDate")),
            u.get("udlogin") or "",
            int(u.get("glops") or 0),
            int(u.get("thumbups") or 0),
            int(u.get("thumbdowns") or 0),
            1,  # permissionSubmitItems
            int(u.get("permissionOpenBBS") if u.get("permissionOpenBBS") is not None else 1),
            1, 1,
            int(u.get("ojuice") or 0) if u.get("ojuice") is not None else 0,
            int(u.get("slengpung") or 0) if u.get("slengpung") is not None else 0,
            int(u.get("csdb") or 0),
            int(u.get("zxdemo") or 0),
            int(u.get("demozoo") or 0) if u.get("demozoo") is not None else 0,
            None, None, None, None, None,
        ))
    upsert(cur, "users",
           ["id", "nickname", "email", "level", "avatar", "registerDate", "udlogin",
            "glops", "thumbups", "thumbdowns", "permissionSubmitItems", "permissionOpenBBS",
            "permissionPostBBS", "permissionPostOneliner", "ojuice", "slengpung", "csdb",
            "zxdemo", "demozoo", "lastip", "lasthost", "lastlogin", "sceneIDData", "sceneIDLastRefresh"],
           user_rows, pk="id")
    print(f"users: {len(user_rows)}")

    # usersettings
    us_rows = [(uid,) for uid in users.keys()]
    # insert ignore
    for uid in users.keys():
        cur.execute("INSERT IGNORE INTO `usersettings` (`id`) VALUES (%s)", (uid,))
    print(f"usersettings: {len(users)}")

    # --- fake oneliners ---
    # the public dump has no oneliner data, so seed some for local development
    cur.execute("SELECT COUNT(*) FROM `oneliner`")
    if cur.fetchone()[0] == 0 and users:
        fake_oneliners = [
            "welcome to the all new pouet api",
            "testing the oneliner endpoint with some fake data",
            "scene is not dead, it just smells funny",
            "lorem ipsum dolor sit amet, consectetur adipiscing elit",
            "check out https://www.pouet.net/ for more",
            "now with 100% more json",
            "back to basics, one line at a time",
            "does anyone still read these?",
            "compiling...",
            "i came here for the prods, i stayed for the oneliner",
        ]
        oneliner_user_ids = [uid for uid in users.keys() if uid]
        now = _dt.datetime.now()
        for i, message in enumerate(fake_oneliners):
            who = random.choice(oneliner_user_ids)
            added = (now - _dt.timedelta(days=i)).strftime("%Y-%m-%d %H:%M:%S")
            cur.execute(
                "INSERT INTO `oneliner` (`message`,`who`,`addedDate`) VALUES (%s,%s,%s)",
                (message, who, added))
        print(f"oneliner: {len(fake_oneliners)}")
    else:
        print("oneliner: skipped (table not empty)")

    # --- platforms ---
    platforms = {}
    for p in prods_data["prods"]:
        for pid, info in (p.get("platforms") or {}).items():
            platforms[int(pid)] = info.get("name") if isinstance(info, dict) else str(info)
    # also need icon; dump has icon in platform info
    platform_icons = {}
    for p in prods_data["prods"]:
        for pid, info in (p.get("platforms") or {}).items():
            if isinstance(info, dict):
                platform_icons[int(pid)] = info.get("icon") or ""
    p_rows = []
    for pid, name in platforms.items():
        p_rows.append((pid, name, platform_icons.get(pid, "")))
    upsert(cur, "platforms", ["id", "name", "icon"], p_rows, pk="id")
    print(f"platforms: {len(p_rows)}")

    # --- compotypes (from placings compo_name + compo id?) ---
    # compotypes has id, componame. Dumps give compo (id) and compo_name (name) in placings.
    compos = {}
    for p in prods_data["prods"]:
        for pl in (p.get("placings") or []):
            cid = pl.get("compo")
            cname = pl.get("compo_name")
            if cid is not None and cname:
                try:
                    compos[int(cid)] = cname
                except (ValueError, TypeError):
                    pass
    # also prod.party_compo is an id; but name unknown -> skip
    c_rows = [(cid, cname) for cid, cname in compos.items()]
    upsert(cur, "compotypes", ["id", "componame"], c_rows, pk="id")
    print(f"compotypes: {len(c_rows)}")

    # --- groups ---
    g_rows = []
    for g in groups_data["groups"]:
        g_rows.append((
            int(g["id"]),
            g.get("name") or "",
            g.get("acronym") or "",
            g.get("disambiguation") or "",
            g.get("web") or "",
            int(g.get("addedUser") or 1),
            to_date(g.get("addedDate")),
            int(g.get("views") or 0) if g.get("views") is not None else None,
            int(g.get("csdb") or 0),
            int(g.get("zxdemo") or 0),
            int(g.get("demozoo")) if g.get("demozoo") not in (None, "") else None,
        ))
    upsert(cur, "groups",
           ["id", "name", "acronym", "disambiguation", "web", "addedUser", "addedDate",
            "views", "csdb", "zxdemo", "demozoo"],
           g_rows, pk="id")
    print(f"groups: {len(g_rows)}")

    # --- parties ---
    pt_rows = []
    for pt in parties_data["parties"]:
        pt_rows.append((
            int(pt["id"]),
            pt.get("name") or "",
            pt.get("web") or "",
            int(pt.get("addedUser") or 0),
            to_date(pt.get("addedDate")),
        ))
    upsert(cur, "parties", ["id", "name", "web", "addedUser", "addedDate"], pt_rows, pk="id")
    print(f"parties: {len(pt_rows)}")

    # --- boards ---
    b_rows = []
    for b in boards_data["boards"]:
        b_rows.append((
            int(b["id"]),
            b.get("name") or "",
            b.get("sysop") or "",
            to_date(b.get("started")),
            to_date(b.get("closed")),
            b.get("phonenumber") or "",
            b.get("telnetip") if b.get("telnetip") not in (None, "") else "",
            1,  # addedUser
            to_date(b.get("addedDate")),
        ))
    upsert(cur, "boards",
           ["id", "name", "sysop", "started", "closed", "phonenumber", "telnetip",
            "addedUser", "addedDate"],
           b_rows, pk="id")
    print(f"boards: {len(b_rows)}")

    # --- prods ---
    prod_rows = []
    for p in prods_data["prods"]:
        # type: dump gives "type" (string) and "types" (list). DB type is set(...) comma-joined.
        types = p.get("types")
        if isinstance(types, list):
            type_str = ",".join(types)
        else:
            type_str = p.get("type") or ""
        # party
        party = p.get("party")
        party_id = None
        if isinstance(party, dict):
            party_id = int(party["id"]) if party.get("id") not in (None, "") else None
        elif party not in (None, ""):
            try:
                party_id = int(party)
            except (ValueError, TypeError):
                party_id = None
        # groups
        groups = p.get("groups") or []
        g1 = int(groups[0]["id"]) if (len(groups) > 0 and isinstance(groups[0], dict)) else (int(groups[0]) if len(groups) > 0 and groups[0] not in (None, "") else None)
        g2 = int(groups[1]["id"]) if (len(groups) > 1 and isinstance(groups[1], dict)) else (int(groups[1]) if len(groups) > 1 and groups[1] not in (None, "") else None)
        g3 = int(groups[2]["id"]) if (len(groups) > 2 and isinstance(groups[2], dict)) else (int(groups[2]) if len(groups) > 2 and groups[2] not in (None, "") else None)
        # invitation
        invitation = p.get("invitation")
        if invitation in (None, ""):
            invitation_id = None
        elif isinstance(invitation, dict):
            invitation_id = int(invitation["id"]) if invitation.get("id") not in (None, "") else None
        else:
            try:
                invitation_id = int(invitation)
            except (ValueError, TypeError):
                invitation_id = None
        # boardID
        board_id = p.get("boardID")
        if board_id in (None, ""):
            board_id = None
        else:
            try:
                board_id = int(board_id)
            except (ValueError, TypeError):
                board_id = None
        # demozoo/csdb/zxdemo/sceneorg
        def to_int_or_none(v):
            if v in (None, ""):
                return None
            try:
                return int(v)
            except (ValueError, TypeError):
                return None
        prod_rows.append((
            int(p["id"]),
            p.get("name") or "",
            p.get("download") or "",
            to_date(p.get("releaseDate")),
            int(p.get("views") or 0),
            int(p["addedUser"]["id"]) if isinstance(p.get("addedUser"), dict) else int(p.get("addedUser") or 1),
            to_date(p.get("addedDate")),
            int(p.get("rank") or 0),
            type_str if type_str else None,
            party_id,
            int(p.get("party_year")) if p.get("party_year") not in (None, "") else None,
            int(p["party_compo"]) if p.get("party_compo") not in (None, "") else None,
            int(p["party_place"]) if p.get("party_place") not in (None, "") else None,
            p.get("latestip") or "",
            g1, g2, g3,
            int(p.get("csdb") or 0),
            int(p.get("zxdemo") or 0),
            to_int_or_none(p.get("demozoo")),
            int(p.get("sceneorg") or 0),
            int(p.get("voteup") or 0),
            int(p.get("votepig") or 0),
            int(p.get("votedown") or 0),
            float(p.get("voteavg") or 0),
            invitation_id,
            int(p.get("invitationyear") or 0),
            board_id,
            0, "", "", "",
        ))
    upsert(cur, "prods",
           ["id", "name", "download", "releaseDate", "views", "addedUser", "addedDate",
            "rank", "type", "party", "party_year", "party_compo", "party_place", "latestip",
            "group1", "group2", "group3", "csdb", "zxdemo", "demozoo", "sceneorg",
            "voteup", "votepig", "votedown", "voteavg", "invitation", "invitationyear",
            "boardID", "DEPRECATED_downloads", "DEPRECATED_downloads_ip", "DEPRECATED_video",
            "DEPRECATED_source"],
           prod_rows, pk="id")
    print(f"prods: {len(prod_rows)}")

    # --- prods_platforms ---
    pp_rows = []
    for p in prods_data["prods"]:
        for pid in (p.get("platforms") or {}).keys():
            try:
                pp_rows.append((int(p["id"]), int(pid)))
            except (ValueError, TypeError):
                pass
    # dedupe
    pp_rows = list(set(pp_rows))
    upsert(cur, "prods_platforms", ["prod", "platform"], pp_rows, pk="prod")
    # upsert with composite pk won't dedupe via on dup; just insert ignore instead
    cur.execute("DELETE FROM `prods_platforms`")
    if pp_rows:
        cur.executemany("INSERT INTO `prods_platforms` (`prod`,`platform`) VALUES (%s,%s)", pp_rows)
    print(f"prods_platforms: {len(pp_rows)}")

    # --- downloadlinks ---
    dl_rows = []
    for p in prods_data["prods"]:
        for dl in (p.get("downloadLinks") or []):
            dl_rows.append((int(p["id"]), dl.get("type") or "", dl.get("link") or ""))
    # dedupe
    seen = set()
    uniq = []
    for r in dl_rows:
        if r in seen:
            continue
        seen.add(r)
        uniq.append(r)
    dl_rows = uniq
    cur.execute("DELETE FROM `downloadlinks`")
    if dl_rows:
        cur.executemany("INSERT INTO `downloadlinks` (`prod`,`type`,`link`) VALUES (%s,%s,%s)", dl_rows)
    print(f"downloadlinks: {len(dl_rows)}")

    # --- credits ---
    cred_rows = []
    for p in prods_data["prods"]:
        for c in (p.get("credits") or []):
            # credit has role, user (dict with id) or userID
            user = c.get("user") if isinstance(c.get("user"), dict) else None
            uid = None
            if user:
                uid = user.get("id")
            elif c.get("userID") not in (None, ""):
                uid = c.get("userID")
            if uid in (None, ""):
                continue
            try:
                uid = int(uid)
            except (ValueError, TypeError):
                continue
            cred_rows.append((int(p["id"]), uid, c.get("role") or ""))
    seen = set()
    uniq = []
    for r in cred_rows:
        if (r[0], r[1]) in seen:
            continue
        seen.add((r[0], r[1]))
        uniq.append(r)
    cred_rows = uniq
    cur.execute("DELETE FROM `credits`")
    if cred_rows:
        cur.executemany("INSERT INTO `credits` (`prodID`,`userID`,`role`) VALUES (%s,%s,%s)", cred_rows)
    print(f"credits: {len(cred_rows)}")

    # --- prodotherparty (from placings where party != prod.party) ---
    pop_rows = []
    for p in prods_data["prods"]:
        prod_party = None
        party = p.get("party")
        if isinstance(party, dict):
            prod_party = party.get("id")
        elif party not in (None, ""):
            prod_party = party
        for pl in (p.get("placings") or []):
            pl_party = pl.get("party")
            pl_party_id = None
            if isinstance(pl_party, dict):
                pl_party_id = pl_party.get("id")
            elif pl_party not in (None, ""):
                pl_party_id = pl_party
            if pl_party_id in (None, ""):
                continue
            try:
                pl_party_id = int(pl_party_id)
            except (ValueError, TypeError):
                continue
            if prod_party is not None and str(pl_party_id) == str(prod_party):
                # primary party already stored in prods; but prodotherparty may still duplicate; skip to avoid dup? 
                # Keep only additional parties. Skip primary.
                continue
            compo = pl.get("compo")
            try:
                compo = int(compo) if compo not in (None, "") else None
            except (ValueError, TypeError):
                compo = None
            place = pl.get("ranking")
            try:
                place = int(place) if place not in (None, "") else None
            except (ValueError, TypeError):
                place = None
            year = pl.get("year")
            try:
                year = int(year) if year not in (None, "") else None
            except (ValueError, TypeError):
                year = None
            pop_rows.append((int(p["id"]), pl_party_id, year or 0, place, compo))
    cur.execute("DELETE FROM `prodotherparty`")
    if pop_rows:
        cur.executemany("INSERT INTO `prodotherparty` (`prod`,`party`,`party_year`,`party_place`,`party_compo`) VALUES (%s,%s,%s,%s,%s)", pop_rows)
    print(f"prodotherparty: {len(pop_rows)}")

    # --- awards: skip (no awards_categories dump) ---

    db.commit()
    cur.execute("SET FOREIGN_KEY_CHECKS=1")

    # --- cache files ---
    cache_dir = os.path.join(args.www_root, "cache")
    os.makedirs(cache_dir, exist_ok=True)
    # pouetbox_stats.cache: empty array serialized in PHP
    with open(os.path.join(cache_dir, "pouetbox_stats.cache"), "w") as f:
        f.write("a:0:{}")
    # remove enum cache so it regenerates from platforms table
    for c in ("enum-platforms.cache", "enum-compotypes.cache"):
        cp = os.path.join(cache_dir, c)
        if os.path.exists(cp):
            os.remove(cp)

    # make cache dir writable
    try:
        os.chmod(cache_dir, 0o777)
    except Exception:
        pass

    cur.close()
    db.close()
    print("Import complete.")


if __name__ == "__main__":
    main()
