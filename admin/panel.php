<?php
session_start();
if(empty($_SESSION['blog_admin'])){ header("Location: /admin/login.php"); exit; }
$config = json_decode(file_get_contents(__DIR__ . "/../config/config.json"), true);
$lang = $_GET['lang'] ?? $config['site']['default_lang'] ?? 'en';
?>
<!doctype html>
<html><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<link rel="stylesheet" href="/assets/css/styles.css"/>
<title>Blog Admin</title>
</head><body>
<div class="bg-art"></div>
<div class="container">
  <div class="panel">
    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
      <div>
        <h2 style="margin:0">Blog Admin</h2>
        <p style="color:var(--muted);margin:6px 0 0">Create/edit posts (stored in <code>/data/posts.json</code>).</p>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn secondary" href="/blog.php?lang=<?php echo htmlspecialchars($lang); ?>">Open Blog</a>
        <a class="btn secondary" href="/admin/logout.php">Logout</a>
      </div>
    </div>

    <div class="kv">
      <div class="panel">
        <h3>Create / Edit</h3>
        <form id="postForm" enctype="multipart/form-data">
          <input type="hidden" name="id" id="postId"/>
          <div style="display:grid;gap:10px">
            <input class="input" name="title" id="title" placeholder="Title" required/>
            <input class="input" name="tags" id="tags" placeholder="Tags (comma separated)"/>
            <input class="input" name="published_at" id="published_at" placeholder="Published date (YYYY-MM-DD)" />
            <select class="select" name="status" id="status">
              <option value="published">Published</option>
              <option value="draft">Draft</option>
            </select>
            <input class="input" type="file" name="cover" id="cover" accept="image/*"/>
            <textarea class="input" name="content" id="content" placeholder="HTML content" rows="10" required></textarea>
            <button class="btn" type="submit">Save</button>
            <button class="btn secondary" type="button" id="btnReset">Reset</button>
          </div>
        </form>
        <p id="msg" style="color:var(--muted);margin-top:10px"></p>
      </div>

      <div class="panel">
        <h3>Posts</h3>
        <div id="adminList" style="display:grid;gap:10px"></div>
      </div>
    </div>
  </div>
</div>

<script>
async function api(path, opts={}){
  const res = await fetch(path, {credentials:"same-origin", ...opts});
  const data = await res.json().catch(()=>({error:"Bad JSON"}));
  if(!res.ok) throw data;
  return data;
}
function esc(s){return (s||"").replace(/[&<>"']/g, m=>({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[m]));}
async function load(){
  const d = await api("/api/blog.php?action=list&all=1");
  const wrap = document.getElementById("adminList");
  wrap.innerHTML = "";
  d.posts.forEach(p=>{
    const div = document.createElement("div");
    div.className="panel";
    div.innerHTML = `
      <div style="display:flex;justify-content:space-between;gap:10px;align-items:center">
        <div style="min-width:0">
          <div style="font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(p.title)}</div>
          <div style="color:var(--muted);font-size:12px">${esc(p.status)} · ${esc(p.published_at||"")}</div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn secondary" data-edit="${esc(p.id)}">Edit</button>
          <button class="btn secondary" data-del="${esc(p.id)}">Delete</button>
        </div>
      </div>
    `;
    wrap.appendChild(div);
  });

  wrap.querySelectorAll("[data-edit]").forEach(b=>b.onclick=async()=>{
    const id = b.getAttribute("data-edit");
    const one = await api("/api/blog.php?action=get&id="+encodeURIComponent(id));
    const p = one.post;
    postId.value = p.id; title.value=p.title; tags.value=(p.tags||[]).join(", ");
    published_at.value=p.published_at||""; status.value=p.status||"published";
    content.value=p.content||"";
    msg.textContent="Loaded: "+p.title;
    window.scrollTo({top:0,behavior:"smooth"});
  });
  wrap.querySelectorAll("[data-del]").forEach(b=>b.onclick=async()=>{
    const id = b.getAttribute("data-del");
    if(!confirm("Delete this post?")) return;
    await api("/api/blog.php?action=delete", {method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify({id})});
    msg.textContent="Deleted.";
    await load();
  });
}

postForm.onsubmit = async (e)=>{
  e.preventDefault();
  const fd = new FormData(postForm);
  const res = await fetch("/api/blog.php?action=save", {method:"POST", body:fd, credentials:"same-origin"});
  const data = await res.json();
  if(!res.ok){ msg.textContent = data.error || "Error"; return; }
  msg.textContent = "Saved: " + data.post.title;
  postForm.reset(); postId.value="";
  await load();
};
btnReset.onclick = ()=>{ postForm.reset(); postId.value=""; msg.textContent=""; };

load().catch(err=>{ msg.textContent = (err && err.error) ? err.error : "Error"; });
</script>
</body></html>
