const modal=document.querySelector('#eventModal');
document.querySelector('#newEvent').onclick=()=>{document.querySelector('#eventForm').reset();modal.classList.remove('hidden')};
document.querySelector('#closeEvent').onclick=()=>modal.classList.add('hidden');
document.querySelector('#eventModal').onclick=e=>{if(e.target.id==='eventModal')modal.classList.add('hidden')};
document.querySelectorAll('.calendar-event').forEach(button=>{
 button.onclick=async()=>{
  if(!confirm('Dit evenement verwijderen?'))return;
  const body=new FormData();
  body.append('delete','1');
  body.append('id',button.dataset.id);
  body.append('csrf',document.querySelector('meta[name=csrf]').content);
  await fetch('api/calendar.php',{method:'POST',body});
  location.reload();
 };
});
document.querySelector('#eventForm').onsubmit=async e=>{
 e.preventDefault();
 const form=e.target;
 const status=document.querySelector('#eventStatus');
 status.textContent='Opslaan…';
 try{
  const body=new FormData(form);
  body.append('csrf',document.querySelector('meta[name=csrf]').content);
  const response=await fetch('api/calendar.php',{method:'POST',body});
  const data=await response.json();
  if(!response.ok||!data.ok)throw Error(data.error||'Opslaan mislukt');
  location.reload();
 }catch(error){status.textContent=error.message}
};
