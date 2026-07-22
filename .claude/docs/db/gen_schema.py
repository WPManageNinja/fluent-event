#!/usr/bin/env python3
"""Single source for Fluent Events DB artifacts — emits schema.sql, schema.dbml,
schema.svg from one spec. Run:  python3 gen_schema.py   (writes all three).

Model: event = CPT hybrid. Identity in wp_posts, flexible/config in wp_postmeta,
typed relational data in wp_fe_event_details (post_id 1:1). Classification
(event_type / venue / organizer) = WP taxonomies + term meta. Roster performer/
speaker = deferred (future CPT). Ticketing custom tables FK to the event.
"""
import html, os

DIR = os.path.dirname(os.path.abspath(__file__))

# ---- table spec: name -> (kind, x, y, [(col, type, flags)]) ----
# flags: P pk · F fk · U unique · ! not null · C default CURRENT_TIMESTAMP ·
#        T default CURRENT_TIMESTAMP on update · (blank = nullable)
# kind: 'core' WP stub · 'tax' WP taxonomy stub · 'app' our custom table
COL0, COL1, COL2, COL3 = 40, 520, 1000, 1480

T = {
 "wp_users": ("core", COL0, 40, [
    ("ID","BIGINT UNSIGNED","P"),("user_login","VARCHAR(60)","!"),
    ("user_email","VARCHAR(100)","!"),("display_name","VARCHAR(250)","!")]),
 "wp_fe_calendars": ("app", COL0, 190, [
    ("id","BIGINT UNSIGNED","P"),("title","VARCHAR(255)","!"),("slug","VARCHAR(255)","U!"),
    ("description","TEXT",""),("visibility","VARCHAR(64)","!"),
    ("settings","JSON",""),("status","VARCHAR(64)","!"),("created_by","BIGINT UNSIGNED","F!"),
    ("created_at","TIMESTAMP","C"),("updated_at","TIMESTAMP","T")]),
 "wp_fe_meta": ("app", COL0, 470, [
    ("id","BIGINT UNSIGNED","P"),("object_type","VARCHAR(64)","!"),
    ("object_id","BIGINT UNSIGNED",""),("meta_key","VARCHAR(255)","!"),
    ("value","LONGTEXT",""),("created_at","TIMESTAMP","C"),("updated_at","TIMESTAMP","T")]),
 "wp_fe_notifications": ("app", COL0, 660, [
    ("id","BIGINT UNSIGNED","P"),("user_id","BIGINT UNSIGNED","F"),
    ("action","VARCHAR(64)","!"),
    ("object_type","VARCHAR(64)",""),("object_id","BIGINT UNSIGNED",""),
    ("title","VARCHAR(255)",""),("content","TEXT",""),("route","TEXT",""),
    ("created_at","TIMESTAMP","C")]),

 "wp_posts": ("core", COL1, 40, [
    ("ID","BIGINT UNSIGNED","P"),("post_author","BIGINT UNSIGNED","F!"),
    ("post_title","TEXT","!"),("post_content","LONGTEXT",""),("post_name","VARCHAR(200)","!"),
    ("post_status","VARCHAR(20)","!"),("post_type","VARCHAR(20)","!"),("post_date","DATETIME","!")]),
 "wp_postmeta": ("core", COL1, 250, [
    ("meta_id","BIGINT UNSIGNED","P"),("post_id","BIGINT UNSIGNED","F!"),
    ("meta_key","VARCHAR(255)",""),("meta_value","LONGTEXT","")]),
 "wp_fe_event_details": ("app", COL1, 420, [
    ("id","BIGINT UNSIGNED","P"),("post_id","BIGINT UNSIGNED","FU!"),
    ("status","VARCHAR(64)","!"),("start_at","DATETIME","!"),("end_at","DATETIME","!"),
    ("timezone","VARCHAR(64)","!"),("calendar_id","BIGINT UNSIGNED","F!"),
    ("parent_event_id","BIGINT UNSIGNED","F"),("venue_type","VARCHAR(64)","!"),
    ("form_fields","JSON",""),
    ("created_at","TIMESTAMP","C"),("updated_at","TIMESTAMP","T")]),

 "wp_fe_ticket_types": ("app", COL2, 40, [
    ("id","BIGINT UNSIGNED","P"),("event_id","BIGINT UNSIGNED","F!"),("type","VARCHAR(64)","!"),
    ("description","VARCHAR(255)",""),("price","DECIMAL(10,2)","!"),("slot","INT",""),
    ("sold","INT","!"),("min_per_reg","INT","!"),("max_per_reg","INT",""),
    ("sale_start_at","DATETIME",""),("sale_end_at","DATETIME",""),("status","VARCHAR(64)","!"),
    ("sort_order","INT","!"),("settings","JSON",""),
    ("created_at","TIMESTAMP","C"),("updated_at","TIMESTAMP","T")]),
 "wp_fe_guests": ("app", COL2, 400, [
    ("id","BIGINT UNSIGNED","P"),("event_id","BIGINT UNSIGNED","F!"),
    ("calendar_id","BIGINT UNSIGNED","F!"),("registration_no","VARCHAR(64)","!"),
    ("is_buyer","TINYINT(1)","!"),("ticket_type_id","BIGINT UNSIGNED","F"),
    ("name","VARCHAR(255)",""),("email","VARCHAR(192)",""),("user_id","BIGINT UNSIGNED","F"),
    ("status","VARCHAR(64)","!"),("form_response","JSON",""),
    ("hash","VARCHAR(64)","U"),("checked_in_at","DATETIME",""),
    ("payment_status","VARCHAR(64)",""),("payment_ref","VARCHAR(255)",""),
    ("price","DECIMAL(10,2)",""),("paid_at","DATETIME",""),("source","VARCHAR(64)",""),
    ("created_at","TIMESTAMP","C"),("updated_at","TIMESTAMP","T")]),
 "wp_fe_notification_users": ("app", COL2, 1040, [
    ("id","BIGINT UNSIGNED","P"),("object_type","VARCHAR(64)","!"),
    ("notification_type","VARCHAR(64)","!"),("object_id","BIGINT UNSIGNED","!"),
    ("user_id","BIGINT UNSIGNED","F!"),("is_read","TINYINT(1)","!"),
    ("created_at","TIMESTAMP","C"),("updated_at","TIMESTAMP","T")]),

 "wp_terms": ("tax", COL3, 40, [
    ("term_id","BIGINT UNSIGNED","P"),("name","VARCHAR(200)","!"),("slug","VARCHAR(200)","!")]),
 "wp_term_taxonomy": ("tax", COL3, 172, [
    ("term_taxonomy_id","BIGINT UNSIGNED","P"),("term_id","BIGINT UNSIGNED","F!"),
    ("taxonomy","VARCHAR(32)","!"),("description","LONGTEXT",""),
    ("parent","BIGINT UNSIGNED","!"),("count","BIGINT","!")]),
 "wp_term_relationships": ("tax", COL3, 352, [
    ("object_id","BIGINT UNSIGNED","PF"),("term_taxonomy_id","BIGINT UNSIGNED","PF"),
    ("term_order","INT","!")]),
 "wp_termmeta": ("tax", COL3, 484, [
    ("meta_id","BIGINT UNSIGNED","P"),("term_id","BIGINT UNSIGNED","F!"),
    ("meta_key","VARCHAR(255)",""),("meta_value","LONGTEXT","")]),
}

# indexes: table -> [(name, [cols], unique_bool)]
IDX = {
 "wp_fe_meta": [("idx_meta_lookup",["object_type","object_id","meta_key"],False)],
 # ~10-100 rows ever: a full scan is 1-2 pages, cheaper than index descent +
 # back-to-table random reads. visibility/status are also 3- and 2-value columns.
 # Only the slug unique stays — it's correctness (no dup slugs), lookup is a side effect.
 "wp_fe_calendars": [("uq_calendars_slug",["slug"],True)],
 "wp_fe_event_details": [("uq_events_post",["post_id"],True),
    ("idx_event_status",["status"],False),("idx_event_window",["start_at","end_at"],False)],
 "wp_fe_ticket_types": [("idx_ticket_event_status",["event_id","status"],False)],
 "wp_fe_guests": [("uq_guests_hash",["hash"],True),
    ("idx_guest_event_status",["event_id","status"],False),
    ("idx_guest_calendar",["calendar_id","status"],False),
    ("idx_guest_order",["registration_no"],False),("idx_guest_email",["email"],False)],
 "wp_fe_notifications": [("idx_notif_object",["object_type","object_id"],False),
    ("idx_notif_action",["action"],False)],
 "wp_fe_notification_users": [
    ("uq_notif_user",["user_id","object_type","object_id","notification_type"],True),
    ("idx_notif_user_inbox",["user_id","is_read","object_type"],False),
    ("idx_notif_user_object",["object_id","is_read","object_type","notification_type"],False)],
 "wp_term_taxonomy": [("uq_term_taxonomy",["term_id","taxonomy"],True)],
}

# FK: (child, col, parent, pcol, on_delete)
FK = [
 ("wp_fe_calendars","created_by","wp_users","ID","RESTRICT"),
 ("wp_posts","post_author","wp_users","ID","RESTRICT"),
 ("wp_postmeta","post_id","wp_posts","ID","CASCADE"),
 ("wp_fe_event_details","post_id","wp_posts","ID","CASCADE"),
 ("wp_fe_event_details","calendar_id","wp_fe_calendars","id","RESTRICT"),
 ("wp_fe_event_details","parent_event_id","wp_fe_event_details","id","CASCADE"),
 ("wp_fe_ticket_types","event_id","wp_fe_event_details","id","CASCADE"),
 ("wp_fe_guests","event_id","wp_fe_event_details","id","CASCADE"),
 ("wp_fe_guests","calendar_id","wp_fe_calendars","id","RESTRICT"),
 ("wp_fe_guests","ticket_type_id","wp_fe_ticket_types","id","RESTRICT"),
 ("wp_fe_guests","user_id","wp_users","ID","SET NULL"),
 ("wp_fe_notifications","user_id","wp_users","ID","SET NULL"),
 ("wp_fe_notification_users","user_id","wp_users","ID","CASCADE"),
 ("wp_term_taxonomy","term_id","wp_terms","term_id","CASCADE"),
 ("wp_termmeta","term_id","wp_terms","term_id","CASCADE"),
 ("wp_term_relationships","term_taxonomy_id","wp_term_taxonomy","term_taxonomy_id","CASCADE"),
 ("wp_term_relationships","object_id","wp_posts","ID","CASCADE"),
]

# Emit only our wp_fe_* tables. WP core (wp_posts/postmeta/users) + taxonomy
# tables are NOT drawn — their USAGE is documented in db/implementation.md + the FR
# docs. FK columns that point at WP core (post_id, created_by, user_id)
# stay as columns (red diamond = external ref); their FK is enforced in app, not DB
# (dbDelta strips FKs to core; engine/collation portability). App↔app FKs are kept.
_LAYOUT = {
    "wp_fe_calendars":          (COL0, 70),
    "wp_fe_meta":               (COL0, 364),
    "wp_fe_notifications":      (COL0, 568),
    "wp_fe_notification_users": (COL0, 808),
    "wp_fe_event_details":             (COL1, 70),
    "wp_fe_ticket_types":       (COL2, 70),
    "wp_fe_guests":             (COL2, 436),
}
T = {n: (T[n][0], x, y, T[n][3]) for n, (x, y) in _LAYOUT.items()}
FK = [f for f in FK if f[0] in T and f[2] in T]   # keep app↔app FKs only

# taxonomies + term-meta docs (WP-native, not our tables)
TAXONOMIES = [
 ("fevents_event_type", "hierarchical", "category/type of event",
    ["fevents_color","fevents_icon_id"]),
 ("fevents_venue", "hierarchical", "where (view-only place)",
    ["fevents_address","fevents_city","fevents_map_url","fevents_lat","fevents_lng"]),
 ("fevents_organizer", "hierarchical", "who runs it (reusable across events)",
    ["fevents_email","fevents_phone","fevents_website","fevents_social","fevents_logo_id"]),
 ("fevents_performer", "flat", "speaker/artist (thin: name + photo, no profile page)",
    ["fevents_photo_id","fevents_designation","fevents_social"]),
]

def esc(s): return html.escape(s, quote=False)
def pk_cols(cols): return [c for c,_,f in cols if "P" in f]
def col_notnull(f): return any(x in f for x in ("!","P","C","T"))

# ============================== SQL ==============================
def sql_type(t, f):
    s = t
    if "P" in f and "F" not in f: s += " NOT NULL AUTO_INCREMENT"   # surrogate id only
    elif "P" in f: s += " NOT NULL"                                  # composite PK member (also FK)
    elif "C" in f: s += " NOT NULL DEFAULT CURRENT_TIMESTAMP"
    elif "T" in f: s += " NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    elif "!" in f: s += " NOT NULL"
    else: s += " NULL"
    return s

def emit_sql():
    L = ["-- Fluent Events — Database Schema (MySQL). GENERATED by gen_schema.py — do not hand-edit.",
     "-- Only our wp_fe_* tables are defined here. WP core tables (wp_posts,",
     "-- wp_postmeta, wp_users) and the taxonomy tables are NOT created by us — we",
     "-- REFERENCE them (see footer + db/implementation.md for how). FK columns that",
     "-- point at WP core (post_id, created_by, user_id) carry no DB FK —",
     "-- enforced in app (dbDelta strips core FKs; engine/collation portability).",
     "-- Event = CPT hybrid (wp_posts + wp_postmeta + wp_fe_event_details post_id 1:1).",
     "-- Ticketing: event -> ticket_types -> guests (1 row per ticket; the is_buyer=1 row",
     "-- of an order carries order/payment fields, NULL on companion rows).",
     "", "SET FOREIGN_KEY_CHECKS = 0;", ""]
    fk_by = {}
    for c,col,p,pc,od in FK: fk_by.setdefault(c, []).append((col,p,pc,od))
    for name,(kind,x,y,cols) in T.items():
        L.append(f"CREATE TABLE `{name}` (")
        body = [f"  `{c}` {sql_type(t,f)}" for c,t,f in cols]
        pks = pk_cols(cols)
        if pks: body.append(f"  PRIMARY KEY ({', '.join('`'+c+'`' for c in pks)})")
        for iname,icols,uniq in IDX.get(name, []):
            kw = "UNIQUE KEY" if uniq else "KEY"
            body.append(f"  {kw} `{iname}` ({', '.join('`'+c+'`' for c in icols)})")
        for col,p,pc,od in fk_by.get(name, []):
            cn = f"fk_{name.replace('wp_fe_','').replace('wp_','')}_{col}"
            body.append(f"  CONSTRAINT `{cn}` FOREIGN KEY (`{col}`) REFERENCES `{p}` (`{pc}`) ON DELETE {od}")
        L.append(",\n".join(body))
        stub = "  -- WP core (stub; real table owned by WordPress)" if kind in ("core","tax") else ""
        L.append(f") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;{('  '+stub) if stub else ''}")
        L.append("")
    L.append("SET FOREIGN_KEY_CHECKS = 1;")
    L.append("")
    L.append("-- ============================================================")
    L.append("-- CLASSIFICATION = WordPress taxonomies (NOT custom tables).")
    L.append("-- Registered against post_type 'fevents_event'; terms live in")
    L.append("-- wp_terms/wp_term_taxonomy, links in wp_term_relationships,")
    L.append("-- extra fields in wp_termmeta. One term reused across many events.")
    for tax,hier,purpose,metas in TAXONOMIES:
        L.append(f"--   {tax}  ({hier}) — {purpose}")
        L.append(f"--       term meta: {', '.join(metas)}")
    L.append("-- Sponsors = deferred (future taxonomy 'fevents_sponsor' if needed).")
    L.append("-- ============================================================")
    open(os.path.join(DIR,"schema.sql"),"w").write("\n".join(L)+"\n")

# ============================== DBML ==============================
def dbml_type(t):
    return t.lower().replace(" unsigned","").replace("bigint","bigint").split("(")[0]

def emit_dbml():
    L = ["// Fluent Events — DBML (dbdiagram.io). GENERATED by gen_schema.py — do not hand-edit.",
     "// Event = CPT hybrid (wp_posts + wp_postmeta + wp_fe_event_details). Classification = WP taxonomies.",""]
    fk_by = {}
    for c,col,p,pc,od in FK: fk_by.setdefault((c,col), (p,pc))
    for name,(kind,x,y,cols) in T.items():
        L.append(f"Table {name} {{")
        for c,t,f in cols:
            attrs = []
            if "P" in f:
                attrs.append("primary key")
                if "F" not in f: attrs.append("increment")
            if "U" in f: attrs.append("unique")
            if col_notnull(f) and "P" not in f: attrs.append("not null")
            ref = fk_by.get((name,c))
            if ref: attrs.append(f"ref: > {ref[0]}.{ref[1]}")
            a = f" [{', '.join(attrs)}]" if attrs else ""
            L.append(f"  {c} {dbml_type(t)}{a}")
        idxs = IDX.get(name, [])
        if idxs:
            L.append("  indexes {")
            for iname,icols,uniq in idxs:
                cols_s = icols[0] if len(icols)==1 else "("+", ".join(icols)+")"
                u = ", unique" if uniq else ""
                L.append(f"    {cols_s} [name: '{iname}'{u}]")
            L.append("  }")
        L.append("}")
        L.append("")
    L.append("// Taxonomies (WP-native, registered on post_type fevents_event):")
    for tax,hier,purpose,metas in TAXONOMIES:
        L.append(f"//   {tax} ({hier}) — {purpose}; term meta: {', '.join(metas)}")
    open(os.path.join(DIR,"schema.dbml"),"w").write("\n".join(L)+"\n")

# ============================== SVG ==============================
HEADER, ROW, FOOT = 22, 18, 16
def hgt(cols): return HEADER + len(cols)*ROW + FOOT
def right(name): return T[name][1] + 300
def cxx(name): return T[name][1] + 150
def bottom(name): return T[name][2] + hgt(T[name][3])
def row_center(name, col):
    _,x,y,cols = T[name]
    for i,(c,_,_) in enumerate(cols):
        if c==col: return y + HEADER + i*ROW + 9
    raise KeyError(f"{name}.{col}")

def key_icon(ix,cy):
    return (f'<circle cx="{ix}" cy="{cy}" r="3.2" fill="none" stroke="#c99a08" stroke-width="1.6"/>'
        f'<path d="M{ix+2.5},{cy} L{ix+8},{cy} M{ix+6},{cy} L{ix+6},{cy+2.5} '
        f'M{ix+8},{cy} L{ix+8},{cy+2.5}" stroke="#c99a08" stroke-width="1.6" fill="none"/>')
def diamond(dx,cy,fill,stroke,sw):
    return (f'<path d="M{dx},{cy-4} L{dx+4},{cy} L{dx},{cy+4} L{dx-4},{cy} Z" '
        f'fill="{fill}" stroke="{stroke}" stroke-width="{sw}"/>')

def table_svg(name):
    kind,x,y,cols = T[name]
    n=len(cols); bh=hgt(cols)
    tax = (kind=="tax")
    grad = "url(#tt)" if tax else "url(#tb)"
    hstroke = "#9a8fb0" if tax else "#8a99ab"
    tag = "#7a5fa0" if tax else "#4a6fa5"
    o=["<g>",
     f'<rect x="{x}" y="{y}" width="300" height="{bh}" rx="4" fill="#fff" stroke="{hstroke}" stroke-width="1.2"/>',
     f'<path d="M{x},{y+4} a4,4 0 0 1 4,-4 h292 a4,4 0 0 1 4,4 v18 h-300 z" fill="{grad}" stroke="{hstroke}" stroke-width="0.8"/>',
     f'<rect x="{x+8}" y="{y+7}" width="10" height="8" rx="1.5" fill="{tag}"/>',
     f'<text x="{x+24}" y="{y+15}" font-size="11.5" font-weight="700" fill="#1c2b3a">{esc(name)}</text>']
    for i,(c,t,f) in enumerate(cols):
        cy=y+HEADER+i*ROW+9
        if "P" in f: o.append(key_icon(x+11,cy)); w="700"
        elif "F" in f: o.append(diamond(x+12,cy,"#c0453b","#8a2820","0.6")); w="400"
        elif col_notnull(f): o.append(diamond(x+12,cy,"#3d6fa0","#2a5580","0.6")); w="400"
        else: o.append(diamond(x+12,cy,"#eef3f8","#5b7fa6","1.1")); w="400"
        o.append(f'<text x="{x+22}" y="{cy+3}" font-size="10.5" fill="#1a1a1a">'
                 f'<tspan font-weight="{w}">{esc(c)}</tspan> <tspan fill="#5a6470">{esc(t)}</tspan></text>')
    iy=y+HEADER+n*ROW
    o.append(f'<rect x="{x}" y="{iy}" width="300" height="16" fill="{"#ddd3ea" if tax else "#c3cedb"}"/>')
    o.append(f'<text x="{x+8}" y="{iy+11}" font-size="9.5" font-weight="700" fill="#3a4a5a">Indexes</text>')
    o.append("</g>")
    return "\n".join(o)

lane_ctr, entry_ctr = {}, {}
def lane_x(region, base):
    i=lane_ctr.get(region,0); lane_ctr[region]=i+1; return base+i*22
def entry_dy(key):
    i=entry_ctr.get(key,0); entry_ctr[key]=i+1; return [0,-6,6,-12,12,-18,18][i%7]

def edge(ct,cc,pt,pc):
    cy1=row_center(ct,cc); cy2=row_center(pt,pc)+entry_dy((pt,pc))
    if abs(cxx(ct)-cxx(pt))<1:                       # same column
        gx=max(right(ct),right(pt)); lx=lane_x(("R",round(gx)),gx+44)
        d=f"M{right(ct)},{cy1} H{lx} V{cy2} H{right(pt)}"
    else:
        if cxx(pt)>cxx(ct): cx1,px=right(ct),T[pt][1]
        else: cx1,px=T[ct][1],right(pt)
        lx=lane_x(("H",round(min(cx1,px)),round(max(cx1,px))),(cx1+px)/2-8)
        d=f"M{cx1},{cy1} H{lx} V{cy2} H{px}"
    return d

# connectors coloured by the table they POINT TO (the "one" side) so you can trace clusters
PARENT_COLOR = {
 "wp_fe_event_details":     "#2f6fd0",  # blue   — things that belong to an event
 "wp_posts":              "#2f9e5b",  # green  — the CPT identity / media
 "wp_users":              "#d98a1a",  # amber  — people (author/host/recipient)
 "wp_fe_calendars":  "#1298a4",  # teal   — calendar grouping
 "wp_fe_ticket_types":"#8a7514", # olive  — ticket tier -> guest
 "wp_terms":              "#8a5a2b",  # brown  — taxonomy term
 "wp_term_taxonomy":      "#6a5acd",  # violet — term-in-taxonomy
}
def _cid(hexc): return "c"+hexc.lstrip("#")

def emit_svg():
    W = max(right(n) for n in T)+40
    H = max(bottom(n) for n in T)+70
    used = []
    for c,col,p,pc,od in FK:
        if p not in [u[0] for u in used]:
            used.append((p, PARENT_COLOR.get(p, "#7d8b99")))
    P=[f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}" font-family="Menlo,Consolas,monospace">']
    defs=['<defs>',
     '<linearGradient id="tb" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#d5dfea"/><stop offset="1" stop-color="#b3c2d4"/></linearGradient>',
     '<linearGradient id="tt" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#e2daf0"/><stop offset="1" stop-color="#cdc0e6"/></linearGradient>']
    for p,c in used:
        k=_cid(c)
        defs.append(f'<marker id="cf_{k}" markerWidth="22" markerHeight="22" refX="17" refY="11" orient="auto" markerUnits="userSpaceOnUse"><path d="M17,11 L3,3 M17,11 L3,11 M17,11 L3,19" fill="none" stroke="{c}" stroke-width="1.6"/></marker>')
        defs.append(f'<marker id="one_{k}" markerWidth="16" markerHeight="22" refX="12" refY="11" orient="auto" markerUnits="userSpaceOnUse"><path d="M7,3 L7,19 M11,3 L11,19" fill="none" stroke="{c}" stroke-width="1.6"/></marker>')
    defs.append('</defs>')
    P.append("".join(defs))
    P.append(f'<rect x="0" y="0" width="{W}" height="{H}" fill="#fbfcfd"/>')
    grid=[f"M{gx},0 V{H}" for gx in range(0,W+1,20)]+[f"M0,{gy} H{W}" for gy in range(0,H+1,20)]
    P.append(f'<path d="{" ".join(grid)}" stroke="#eceff2" stroke-width="1"/>')
    P.append(f'<text x="40" y="26" font-size="15" font-weight="700" fill="#1c2b3a">Fluent Events — DB schema (event = CPT hybrid + WP taxonomies)</text>')
    P.append(f'<text x="40" y="44" font-size="10" fill="#6a7684">Only wp_fe_* tables shown; WP core (posts/meta/users/taxonomy) is referenced, not drawn — see the note + implementation.md. Line colour = the table it points to.</text>')
    for c,col,p,pc,od in FK:
        clr=PARENT_COLOR.get(p,"#7d8b99"); k=_cid(clr)
        P.append(f'<path d="{edge(c,col,p,pc)}" fill="none" stroke="{clr}" stroke-width="1.5" opacity="0.9" marker-start="url(#cf_{k})" marker-end="url(#one_{k})"/>')
    for name in T: P.append(table_svg(name))
    # connector legend (below events, free space)
    lx,ly=COL1, 440
    P.append(f'<g><rect x="{lx}" y="{ly}" width="430" height="{28+len(used)*20}" rx="5" fill="#fff" stroke="#8a99ab" stroke-width="1"/>')
    P.append(f'<text x="{lx+12}" y="{ly+20}" font-size="11" font-weight="700" fill="#1c2b3a">Connector colour = points to&#8230;</text>')
    for i,(p,c) in enumerate(used):
        yy=ly+40+i*20
        P.append(f'<path d="M{lx+14},{yy-3} h26" stroke="{c}" stroke-width="2.2"/>')
        P.append(f'<text x="{lx+50}" y="{yy}" font-size="10" fill="#33404d">{esc(p)}</text>')
    P.append('</g>')
    # WP-core usage note (core tables not drawn — documented in implementation.md)
    nx,ny=COL1, 640
    P.append(f'<g><rect x="{nx}" y="{ny}" width="440" height="164" rx="4" fill="#fff" stroke="#9a8fb0" stroke-width="1"/>')
    P.append(f'<text x="{nx+12}" y="{ny+20}" font-size="10.5" font-weight="700" fill="#4a3a5a">WP core (not drawn — see implementation.md)</text>')
    lines=["event   = wp_posts (post_type=fevents_event) + wp_postmeta",
      "classify = WP taxonomies: event_type, venue, organizer,",
      "           performer  (wp_terms / wp_term_relationships)",
      "post_id / created_by / user_id  ->  wp_posts /",
      "           wp_users   (red diamond; FK enforced in app, not DB)",
      "images   = plain URLs in settings JSON (calendar logo/cover,",
      "           event cover) — no attachment FK, no image columns"]
    for i,ln in enumerate(lines):
        P.append(f'<text x="{nx+12}" y="{ny+42+i*19}" font-size="9" fill="#4a5561">{esc(ln)}</text>')
    P.append('</g>')
    P.append("</svg>")
    open(os.path.join(DIR,"schema.svg"),"w").write("\n".join(P))

emit_sql(); emit_dbml(); emit_svg()
print("wrote schema.sql, schema.dbml, schema.svg")
