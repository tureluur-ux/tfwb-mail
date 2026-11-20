const $=s=>document.querySelector(s);let folder='INBOX',current=null,selected=new Set();
const names={INBOX:'Inbox',Sent:'Verzonden',Drafts:'Concepten',Junk:'Spam',Trash:'Prullenbak'};
function toast(t){$('#toast').textContent=t;$('#toast').classList.add('show');setTimeout(()=>$('#toast').classList.remove('show'),3500)}
async function api(url,opts={}){
 let r;
 try{r=await fetch(url,opts)}catch(error){throw Error('Server niet bereikbaar. tfwbmail opnieuw starten.')}
 let d;
 try{d=await r.json()}catch{throw Error(r.status===302?'Sessie verlopen: log opnieuw in.':'Server gaf geen geldige reactie.')}
 if(!r.ok||!d.ok)throw Error(d.error||'Er ging iets mis.');
 return d
}
async function load(){selected.clear();updateDelete();$('#messages').innerHTML='<div class="empty">Berichten laden…</div>';$('#reader').innerHTML='<div class="welcome"><div class="welcome-icon">✉</div><h2>Selecteer een bericht</h2><p>Kies links een e-mail om deze te openen.</p></div>';try{let d=await api('api/messages.php?folder='+encodeURIComponent(folder)+'&q='+encodeURIComponent($('#search').value));$('#countLabel').textContent=d.count+' berichten';$('#messages').innerHTML=d.messages.length?d.messages.map(m=>`<div class="message-row ${m.seen?'':'unread'}" data-uid="${m.uid}"><input type="checkbox" class="message-check" aria-label="Bericht selecteren"><button class="message" data-uid="${m.uid}"><span class="dot"></span><span class="message-copy"><b>${esc(m.from)}</b><strong>${esc(m.subject||'(Geen onderwerp)')}</strong><small>${esc(formatDate(m.date))}</small></span></button></div>`).join(''):'<div class="empty">Geen berichten gevonden.</div>';document.querySelectorAll('.message-row .message').forEach(b=>b.onclick=()=>openMessage(+b.dataset.uid,b));document.querySelectorAll('.message-check').forEach(c=>c.onchange=()=>toggleSelected(c));}catch(e){$('#messages').innerHTML='<div class="empty error">'+esc(e.message)+'</div>';}}
function formatDate(value){const d=new Date(value);if(Number.isNaN(d.valueOf()))return value;const today=new Date();if(d.toDateString()==today.toDateString())return d.toLocaleTimeString('nl-NL',{hour:'2-digit',minute:'2-digit'});return d.toLocaleDateString('nl-NL',{day:'numeric',month:'short'})}
function toggleSelected(c){const row=c.closest('.message-row');const uid=+row.dataset.uid;if(c.checked)selected.add(uid);else selected.delete(uid);row.classList.toggle('selected',c.checked);updateDelete()}
function updateDelete(){const button=$('#deleteSelected');button.disabled=!selected.size;button.textContent=selected.size?'Verwijderen ('+selected.size+')':'Verwijderen'}
async function openMessage(uid,el){document.querySelectorAll('.message').forEach(x=>x.classList.remove('selected'));el.classList.add('selected');$('#reader').innerHTML='<div class="empty">Bericht openen…</div>';try{let d=await api('api/message.php?folder='+encodeURIComponent(folder)+'&uid='+uid);current=d;$('#reader').innerHTML=`<article class="mail"><div class="mail-actions"><button id="reply">↶ Beantwoorden</button><button id="forward">↗ Doorsturen</button></div><h2>${esc(d.subject||'(Geen onderwerp)')}</h2><div class="sender"><div class="avatar">${esc((d.from||'?')[0].toUpperCase())}</div><div><b>${esc(d.from)}</b><small>Aan: ${esc(d.to)} · ${esc(d.date)}</small></div></div><div class="mail-body">${d.body}</div></article>`;$('#reply').onclick=()=>compose(d.from,'Re: '+d.subject,'\\n\\n--- Oorspronkelijk bericht ---\\n'+d.from+' schreef:\\n');$('#forward').onclick=()=>compose('', 'Fwd: '+d.subject,'\\n\\n--- Doorgestuurd bericht ---\\n'+d.body.replace(/<[^>]*>/g,''));}catch(e){$('#reader').innerHTML='<div class="empty error">'+esc(e.message)+'</div>';}}
function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
let draftSaveTimer=null;
async function saveDraft(){
 const form=$('#composeForm');
 if(!form)return;
 const body=new FormData(form);
 body.append('csrf',document.querySelector('meta[name=csrf]').content);
 body.append('id',form.elements.draft_id.value||'');
 body.append('to',body.get('to')||'');
 body.append('body',body.get('body')||'');
 try{
  const response=await fetch('api/drafts.php',{method:'POST',body});
  const data=await response.json();
  if(data.ok){form.elements.draft_id.value=String(data.id);$('#sendStatus').textContent='Concept lokaal opgeslagen';}
 }catch(_error){$('#sendStatus').textContent='Concept kon niet lokaal worden opgeslagen'}
}
function compose(to='',subject='',body=''){$('#composeForm').reset();$('#composeForm [name=draft_id]').value='';$('#composeForm [name=to]').value=to;$('#composeForm [name=subject]').value=subject;$('#composeForm [name=body]').value=body;$('#composeModal').classList.remove('hidden');$('#sendStatus').textContent='';}
['to','subject','body'].forEach(name=>{const field=$('#composeForm [name='+name+']');if(field)field.addEventListener('input',()=>{clearTimeout(draftSaveTimer);draftSaveTimer=setTimeout(saveDraft,900)})});
$('#composeBtn').onclick=()=>compose();$('#closeCompose').onclick=()=>$('#composeModal').classList.add('hidden');$('#composeModal').onclick=e=>{if(e.target.id==='composeModal')$('#composeModal').classList.add('hidden')};
$('#composeForm').onsubmit=async e=>{e.preventDefault();let b=$('#composeForm button[type=submit]');b.disabled=true;$('#sendStatus').textContent='Verzenden…';try{let fd=new FormData(e.target);fd.append('csrf',document.querySelector('meta[name=csrf]')?.content||'');let r=await fetch('api/send.php',{method:'POST',body:fd});let d=await r.json();if(!r.ok||!d.ok)throw Error(d.error);toast('Bericht verzonden');$('#composeModal').classList.add('hidden');}catch(err){$('#sendStatus').textContent=err.message;}finally{b.disabled=false;}};
document.querySelectorAll('.folder').forEach(b=>b.onclick=()=>{document.querySelectorAll('.folder').forEach(x=>x.classList.remove('active'));b.classList.add('active');folder=b.dataset.folder;$('#folderTitle').textContent=names[folder]||folder;load()});
$('#refresh').onclick=load;$('#search').onkeydown=e=>{if(e.key==='Enter')load()};$('#themeBtn').onclick=()=>{document.body.classList.toggle('dark');localStorage.setItem('tb-dark',document.body.classList.contains('dark')?'1':'0')};if(localStorage.getItem('tb-dark')==='1')document.body.classList.add('dark');$('#selectAll').onclick=()=>{const checks=[...document.querySelectorAll('.message-check')];const check=!checks.every(c=>c.checked);checks.forEach(c=>{c.checked=check;toggleSelected(c)})};
$('#deleteSelected').onclick=async()=>{if(!selected.size||!confirm(`Verwijder ${selected.size} bericht(en)?`))return;const fd=new FormData();fd.append('csrf',document.querySelector('meta[name=csrf]')?.content||'');fd.append('folder',folder);selected.forEach(uid=>fd.append('uids[]',String(uid)));try{const r=await fetch('api/delete.php',{method:'POST',body:fd});const d=await r.json();if(!r.ok||!d.ok)throw Error(d.error);toast(d.message);load()}catch(e){toast(e.message)}};
const sidebarToggle=document.querySelector('#sidebarToggle');
const sidebarCollapsed=localStorage.getItem('tfwbmail-sidebar')==='1';
function updateSidebar(collapsed){
 document.body.classList.toggle('sidebar-collapsed',collapsed);
 localStorage.setItem('tfwbmail-sidebar',collapsed?'1':'0');
 if(sidebarToggle){
  sidebarToggle.textContent=collapsed?'›':'‹';
  sidebarToggle.setAttribute('aria-expanded',collapsed?'false':'true');
  sidebarToggle.title=collapsed?'Zijbalk uitvouwen':'Zijbalk inklappen';
 }
}
updateSidebar(sidebarCollapsed);
if(sidebarToggle){
 sidebarToggle.addEventListener('click',()=>{
  updateSidebar(!document.body.classList.contains('sidebar-collapsed'));
 });
}
const tutorial=[
 {title:'Welkom bij tfwbmail',text:'Je lokale postomgeving is klaar. Accounts en lokale agenda/contacten blijven op deze computer.'},
 {title:'Sneller werken',text:'Gebruik zoeken, meerdere berichten selecteren, themawissel, compacte zijbalk en openen met één klik.'},
 {title:'Alles op één plek',text:'Beheer Agenda en Contacten vanuit de zijbalk. Bericht verwijderen zet eerst in de prullenbak; definitief verwijderen gebeurt vanuit de prullenbak.'}
];
let tutorialStep=0;
function showTutorial(){
 const modal=$('#tutorialModal');
 if(!modal||localStorage.getItem('tfwbmail-tutorial')==='1')return;
 modal.classList.remove('hidden');
 document.querySelectorAll('.tutorial-progress span').forEach((s,i)=>s.classList.toggle('active',i===0));
}
function closeTutorial(){localStorage.setItem('tfwbmail-tutorial','1');$('#tutorialModal').classList.add('hidden')}
if($('#tutorialNext')){
 $('#tutorialSkip').onclick=closeTutorial;
 $('#tutorialNext').onclick=()=>{
  tutorialStep++;
  if(tutorialStep>=tutorial.length){closeTutorial();return}
  $('#tutorialTitle').textContent=tutorial[tutorialStep].title;
  $('#tutorialText').textContent=tutorial[tutorialStep].text;
  document.querySelectorAll('.tutorial-progress span').forEach((s,i)=>s.classList.toggle('active',i<=tutorialStep));
  if(tutorialStep===tutorial.length-1)$('#tutorialNext').textContent='Starten';
 };
 showTutorial();
}
load();