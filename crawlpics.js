items = Array.from(document.querySelectorAll('.completed table tbody .list-table-data')).map(el => {
  return {
    title: el.querySelector('.title a').innerText.trim(),
    link: el.querySelector('.title a').href,
    score: parseInt(el.querySelector('.score a span').innerText) || 0,
    type: el.querySelector('.type').innerText.trim(),
    progress: parseInt(el.querySelector('.progress span').innerText.trim()) || 0,
  };
});
items.forEach((i, index) => {
  if (index > 0) {
    // return;
  }
  fetch(`${i.link}/pics`).then(r => r.text()).then(t => {
    const dummy = document.createElement('div');
    dummy.innerHTML = t;
    const picEls = dummy.querySelectorAll('.picSurround');
    const pics = Array.from(picEls).map(el => el.querySelector('a').href);
    i.pics = pics;
    console.log(`${index + 1}/${items.length} done`);
  });
  fetch(`${i.link}`).then(r => r.text()).then(t => {
    const dummy = document.createElement('div');
    dummy.innerHTML = t;
    const h2s = dummy.querySelectorAll('h2');
    h2s.forEach(el => {
      const title = el.innerText;
      if (title === 'Alternative Titles') {
        let next = el.nextElementSibling;
        const altNames = {};
        while(next.tagName === 'DIV') {
          const language = next.childNodes[1].textContent.trim();
          const name = next.childNodes[2].textContent.trim();
          altNames[language] = name;
          next = next.nextElementSibling;
        }
        i.altNames = altNames;
      } else if (title === 'Information') {
        let next = el.nextElementSibling;
        const info = {};
        while(next.tagName === 'DIV') {
          const dumm = document.createElement('div');
          dumm.innerHTML = next.innerHTML;
          const type = dumm.childNodes[1].textContent.trim();
          dumm.querySelectorAll('span').forEach(el => { el.remove() } );
          const data = dumm.textContent.trim().split(',').map(i => i.trim()).join(', ');
          info[type] = data;

          next = next.nextElementSibling;
        }
        i.info = info;
      } else if (title === 'Characters & Voice Actors') {

        const chars = Array.from([
          ...Array.from(el.parentElement.nextElementSibling.children).reduce((c, n) => {
            return [
              ...c,
              ...Array.from(n.children),
            ];
          }, []),
        ]).map(charEl => {
          const dumm = document.createElement('table');
          dumm.innerHTML = charEl.innerHTML;
          const small = dumm.querySelector('small');
          const charRole = small.innerText.trim();
          const smallBig = small.parentElement.parentElement;
          small.remove();
          const charName = smallBig.innerText.replace(/\n/g, '').trim();

          const small2 = dumm.querySelector('small');
          let actorName = '';
          let actorLanguage = '';
          if (small2) {
            actorLanguage = small2.innerText.trim();
            const small2Big = small2.parentElement;
            small2.remove();
            actorName = small2Big.innerText.replace(/\n/g, '').trim();
          }
          return {
            charRole,
            charName,
            actorName,
            actorLanguage
          };
        });
        i.chars = chars;

      } else {
        // console.log(title);
      }

    });

    const synopsisEl = dummy.querySelector('[itemprop=description]');
    i.synopsis = synopsisEl.innerText;

    console.log(`${index + 1}/${items.length} info done`);
  });
});