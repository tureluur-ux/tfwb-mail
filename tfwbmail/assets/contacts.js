const modal=document.querySelector('#contactModal');
document.querySelector('#newContact').onclick=()=>{document.querySelector('#contactForm').reset();modal.classList.remove('hidden')};
document.querySelector('#closeContact').onclick=()=>modal.classList.add('hidden');
modal.onclick=e=>{if(e.target.id==='contactModal')modal.classList.add('hidden')};
document.querySelectorAll('.contact-delete').forEach(button=>{
 button.onclick=async()=>{
  const card=button.closest('.contact-card');
  if(!confirm('Dit contact verwijderen?'))return;
  const body=new FormData();
  body.append('delete','1');body.append('id',card.dataset.id);
  body.append('csrf',document.querySelector('meta[name=csrf]').content);
  const response=await fetch('api/contacts.php',{method:'POST',body});
  const data=await response.json();
  if(response.ok&&data.ok)location.reload();
 };
});
document.querySelector('#contactForm').onsubmit=async e=>{
 e.preventDefault();
 const status=document.querySelector('#contactStatus');
 status.textContent='Opslaan…';
 const body=new FormData(e.target);
 body.append('csrf',document.querySelector('meta[name=csrf]').content);
 try{
  const response=await fetch('api/contacts.php',{method:'POST',body});
  const data=await response.json();
  if(!response.ok||!data.ok)throw Error(data.error||'Opslaan mislukt');
  location.reload();
 }catch(error){status.textContent=error.message}
};
