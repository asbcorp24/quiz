
document.querySelectorAll('[data-autonow]').forEach(el => {
  if (!el.value) {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    el.value = d.toISOString().slice(0,16);
  }
});

const comment = document.getElementById('comment');
const counter = document.getElementById('counter');
if(comment && counter){
  const sync=()=>counter.textContent = `${comment.value.length}/300`;
  comment.addEventListener('input', sync); sync();
}
