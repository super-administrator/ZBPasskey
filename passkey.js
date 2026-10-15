(() => {
'use strict';
const base = new URL('.', document.currentScript.src);
const decode = s => Uint8Array.from(atob(s.replace(/-/g,'+').replace(/_/g,'/')), c=>c.charCodeAt(0));
const encode = b => b ? btoa(String.fromCharCode(...new Uint8Array(b))).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'') : '';
let csrf;
async function api(action, extra={}) {
 const r = await fetch(new URL('api.php',base), {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,csrf,...extra})});
 const j = await r.json(); if (!j.ok) throw Error(j.error); return j.data;
}
function convert(o) {
 o.publicKey.challenge=decode(o.publicKey.challenge);
 if(o.publicKey.user) o.publicKey.user.id=decode(o.publicKey.user.id);
 for(const k of ['allowCredentials','excludeCredentials']) for(const c of o.publicKey[k]||[]) c.id=decode(c.id);
 return o;
}
function packed(c) {
 const r=c.response, v={id:encode(c.rawId),clientDataJSON:encode(r.clientDataJSON)};
 for(const k of ['attestationObject','authenticatorData','signature','userHandle']) if(r[k]) v[k]=encode(r[k]);
 return v;
}
document.addEventListener('DOMContentLoaded', async()=>{
 const manage=!!window.ZBPasskeyConfig;
 let status=document.getElementById('status');
 if(!manage) {
  const box=document.querySelector('.login')||document.body;
  const entry=document.createElement('div');entry.id='pk-entry';
  const title=document.createElement('h2');title.textContent='通行密钥登录';
  const hint=document.createElement('p');hint.textContent='使用指纹、面容或设备 PIN 安全登录';hint.className='pk-hint';
  const b=document.createElement('button'); b.type='button';b.textContent='使用通行密钥登录';b.id='pk-login';b.disabled=true;
  status=document.createElement('p');status.id='pk-status';status.setAttribute('role','status');
  entry.append(title,hint,b,status);box.prepend(entry);
  const style=document.createElement('style');style.textContent=`#pk-entry{max-width:340px;margin:24px auto;text-align:center;font:15px/1.6 system-ui,sans-serif}#pk-entry h2{font-size:24px;font-weight:600;margin:0 0 8px;color:#263445}#pk-entry .pk-hint{margin:0 0 24px;color:#697789}#pk-login{display:block;width:100%;padding:13px 18px;background:#386fa8;color:white;border:0;border-radius:6px;font:inherit;font-weight:600;cursor:pointer}#pk-login:disabled{opacity:.55;cursor:wait}#pk-login:hover:enabled{background:#2d5d90}#pk-fallback{border:0;background:transparent;color:#386fa8;cursor:pointer;font:inherit;padding:8px}#pk-status{font-size:13px;white-space:pre-wrap;color:#a33;margin:10px 0;overflow-wrap:anywhere}.login form[hidden]{display:none!important}`;document.head.append(style);
 }
 const show=e=>{
  const messages={NotAllowedError:'已取消验证，或设备没有可用的通行密钥。请重试或使用密码登录。',InvalidStateError:'此设备的通行密钥已经绑定，请使用已有密钥。',SecurityError:'请检查 HTTPS 和网站域名设置。'};
  status.textContent=messages[e.name]||e.message||String(e);
 };
 const run=fn=>async e=>{e.preventDefault();const b=e.currentTarget;b.disabled=true;status.textContent='请完成设备验证…';try{await fn();}catch(err){show(err);}finally{b.disabled=false;}};
 try {
  if(!isSecureContext||!window.PublicKeyCredential) throw Error('需要 HTTPS 和支持通行密钥的浏览器');
  csrf=manage?window.ZBPasskeyConfig.csrf:(await (await fetch(new URL('options.php',base),{cache:'no-store'})).json()).csrf;
  if(!csrf) throw Error('插件无法初始化，请使用下方密码登录');
  if(!manage) {
   const form=document.querySelector('.login form');
   if(form){
    form.hidden=true;
    const toggle=document.createElement('button');toggle.type='button';toggle.id='pk-fallback';toggle.textContent='使用密码和动态令牌登录';
    toggle.onclick=()=>{form.hidden=!form.hidden;toggle.textContent=form.hidden?'使用密码和动态令牌登录':'收起密码登录';};document.getElementById('pk-entry').append(toggle);
   }
   document.getElementById('pk-login').disabled=false;
   document.getElementById('pk-login').onclick=run(async()=>{
    const c=await navigator.credentials.get(convert(await api('getOptions')));
    const result=await api('getFinish',packed(c));location.assign(result.redirect);
   });return;
  }
  const identity=()=>({password:document.getElementById('pk-password').value,otp:document.getElementById('pk-otp')?.value||''});
 const clearIdentity=()=>{document.getElementById('pk-password').value='';const otp=document.getElementById('pk-otp');if(otp)otp.value='';};
  async function refresh() {
   const list=document.getElementById('pk-list');list.replaceChildren();
   for(const row of await api('list')) {
    const li=document.createElement('li');li.textContent=`${row.name}（${row.created}） `;
    const b=document.createElement('button');b.type='button';b.className='button';b.textContent='删除';
    b.onclick=run(async()=>{await api('remove',{id:row.id,...identity()});clearIdentity();await refresh();status.textContent='已删除';});li.append(b);
    const rename=document.createElement('button');rename.type='button';rename.className='button';rename.textContent='重命名';
    rename.onclick=run(async()=>{const name=prompt('密钥名称',row.name);if(name===null){status.textContent='';return;}await api('rename',{id:row.id,name,...identity()});clearIdentity();await refresh();status.textContent='已重命名';});li.append(rename);list.append(li);
   }
  }
  document.getElementById('pk-create').onclick=run(async()=>{
   const opts=await api('createOptions',{name:document.getElementById('pk-name').value||'通行密钥',...identity()});
   clearIdentity();
   const c=await navigator.credentials.create(convert(opts));await api('createFinish',packed(c));await refresh();status.textContent='绑定成功';
  });await refresh();status.textContent='';
 }catch(e){show(e);}
});
})();
