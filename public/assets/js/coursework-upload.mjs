const owner = document.getElementById('upload-owner');
const assignment = document.getElementById('upload-assignment');
const labels = JSON.parse(document.getElementById('coursework-labels').textContent);

function updateAssignments() {
  const previous = assignment.value;
  const choices = labels[owner.value === 'team' ? 'team' : 'personal'];
  assignment.replaceChildren(...Object.entries(choices).map(([number, label]) => {
    const option = document.createElement('option');
    option.value = number;
    option.textContent = `${number} · ${label}`;
    return option;
  }));
  if (Object.hasOwn(choices, previous)) assignment.value = previous;
}

owner.addEventListener('change', updateAssignments);
updateAssignments();

const source = document.getElementById('upload-source');
const fileField = document.getElementById('upload-file-field');
const linkField = document.getElementById('upload-link-field');
function updateSource() {
  const link = source.value === 'link';
  fileField.hidden = link;
  linkField.hidden = !link;
  fileField.querySelector('input').disabled = link;
  fileField.querySelector('input').required = !link;
  linkField.querySelector('input').disabled = !link;
  linkField.querySelector('input').required = link;
}
source.addEventListener('change', updateSource);
updateSource();
