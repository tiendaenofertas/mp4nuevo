<html>
  <head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Quicksand">
    <style>
      body {
        padding: 0;
        margin: 0;
        color: #fff;
        font-family: 'Quicksand', Corbel, sans-serif;
        background-size: cover;
      }

      #mainContainer {
        transition: opacity 0.3s;
      }
      .anime-box {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 33vh;
        user-select: none;
    
        display: flex;
        align-items: flex-end;
        letter-spacing: 0.05em;
        transition: transform 2s, opacity 2s;
        transition-timing-function: cubic-bezier(0,.7,.3,1);
      }

      #anime {
      }
    
      .anime-pic {
        height: 31vh;
        width: 31vh;
        background-size: contain;
        background-position: right bottom;
        background-repeat: no-repeat;
        flex: 0 0 31vh;
        margin: 3vh 6vh;
      }
    
      .text {
        text-shadow: 0 2px 0 rgba(0, 0, 0, 0.5),
          2px 2px 0 rgba(0, 0, 0, 0.5),
          2px 0 0 rgba(0, 0, 0, 0.5);
      }
    
      .anime-info {
        flex: 1 1 auto;
        padding: 2vh;
      }
      .anime-title {
        font-size: 6vh;
        font-weight: bold;
        color: #fff;
      }
      .anime-eng-title {
        font-size: 2.5vh;
        margin-bottom: 1vh;
      }
    
      @keyframes slide-up {
        0% {
          transform: translateY(100%);
          opacity: 0;
        }
        100% {
          transform: none;
          opacity: 1;
        }
      }
      .anime-synopsis {
        font-size: 2vh;
        animation: slide-up 2s 1;
        animation-timing-function: cubic-bezier(0,.7,.3,1);
      }
      .anime-top-info {
        transition: transform 2s;
        transition-timing-function: cubic-bezier(0,.7,.3,1);
      }
    
      .anime-score > div {
        margin: 1vh 0;
        font-size: 2.5vh;
        font-weight: bold;
        background: #333;
        color: #fff;
        display: inline-block;
        padding: 0.5vh 4vh;
        border-radius: 999px;
        position: relative;
      }
      
      .anime-score > div::before,
      .anime-score > div::after {
        content: '';
        position: absolute;
        top: 50%;
        width: 1.3vh;
        height: 1.3vh;
        border-radius: 1.3vh;
        transform: translateY(-50%);
      }
      .anime-score > div::before {
        left: 1.3vh;
      }
      .anime-score > div::after {
        right: 1.3vh;
      }
      .score-0::before, .score-0::after { background: #ff00bf; }
      .score-1::before, .score-1::after { background: #ff0085; }
      .score-2::before, .score-2::after { background: #ff0048; }
      .score-3::before, .score-3::after { background: #ff000c; }
      .score-4::before, .score-4::after { background: #fd3c00; }
      .score-5::before, .score-5::after { background: #fc8500; }
      .score-6::before, .score-6::after { background: #fad400; }
      .score-7::before, .score-7::after { background: #e1ff05; }
      .score-8::before, .score-8::after { background: #9eff14; }
      .score-9::before, .score-9::after { background: #5cff23; }
      .score-10::before, .score-10::after { background: #06ff36; }
    
      .anime-tags > div {
        display: inline-block;
        background: #000;
        padding: 0.5vh 1.5vh;
        border-radius: 999px;
        font-weight: bold;
        margin: 0 0.5vh 1.5vh;
      }
      .tag-tv > div { background: #008454; }
      .tag-ova > div { background: #00669f; }
      .tag-ona > div { background: #b76e00; }
      .tag-movie > div { background: #7d0078; }
      .tag-special > div { background: #960000; }
    
      #dummy {
        position: fixed;
        right: 100vw;
        bottom: 100vh;
      }
    </style>
  </head>

  <body>
    <!-- <div id="animePast" class="anime-box"></div>
    <div id="anime" class="anime-box"></div> -->
    <div id="mainContainer"></div>

    <script>
      if (location.hash === '#test') {
        document.body.style.backgroundImage = `url('https://cdn1.epicgames.com/ue/product/Screenshot/1-1920x1080-4f7bf484dcbe3ddb99f434cad5de1ed3.jpg?resize=1&w=1600')`;
      }
      const mainContainerEl = document.getElementById('mainContainer');
      const animeEl = document.getElementById('anime');
      const animePastEl = document.getElementById('animePast');
      let items = [];
      let i = 0;
      let prevI = -1;
      let showSynopsis = false;
      let busy = false;
      const transitionTimeouts = {};
      const iLocalStorageKey = 'overlay-anime-index';
      const sLocalStorageKey = 'overlay-anime-show-synopsis';
      let picIndex = 0;
      let titleWithoutSynopsisRect = undefined;

      function setShowSynopsis(mode, override) {
        if (busy) {
          return;
        }
        showSynopsis = mode;
        localStorage.setItem(sLocalStorageKey, mode);
        if (!override) {
          prevI = i;
        }
        showAnime();
      }
      function showNext() {
        if (busy) {
          return;
        }
        prevI = i;
        i++;
        if (i >= items.length) {
          i = 0;
        }
        localStorage.setItem(iLocalStorageKey, i);
        picIndex = 0;
        mainContainer.style.opacity = '0.5';
        setShowSynopsis(false, true);

        showAnime();
      }
      function showPrev() {
        if (busy) {
          return;
        }
        prevI = i;
        i--;
        if (i < 0) {
          i = items.length - 1;
        }
        localStorage.setItem(iLocalStorageKey, i);
        picIndex = 0;
        setShowSynopsis(false, true);

        showAnime();
      }
      function cyclePics() {
        if (busy) {
          return;
        }
        const animeData = items[i];
        if (!animeData) {
          return;
        }
        picIndex++;
        if (picIndex >= animeData.pics.length) {
          picIndex = 0;
        }
        showAnime();
      }

      function readData(data) {
        if (busy) {
          return;
        }
        items = data.items;
        i = (function(){
          let cached = parseInt(localStorage.getItem(iLocalStorageKey));
          if (cached >= items.length || typeof cached !== 'number' || isNaN(cached)) {
            cached = 0;
          }
          return cached;
        })();
        // showSynopsis = (function(){
        //   let cached = localStorage.getItem(sLocalStorageKey) === 'true';
        //   return cached;
        // })();
        mainContainer.style.opacity = '0.5';
        showAnime();
      }

      window.onkeydown = function(e) {
        if (busy) {
          return;
        }
        if (e.which === 40) {
          // key down
          setShowSynopsis(!showSynopsis);
        }
        if (e.which === 37) {
          // key left
          showPrev();
        }
        if (e.which === 39) {
          // key right
          showNext();
        }
        if (e.which === 38) {
          // key up
          cyclePics();
        }
      };

      mainContainer.onclick = function() {
        showNext();
      };

      mainContainer.oncontextmenu = function() {
        showPrev();

        return false;
      };

      mainContainer.onmousewheel = function() {
        cyclePics();
      }

      function showAnime() {
        const animeData = items[i];
        if (!animeData) {
          return;
        }

        const thisPic = animeData.pics[picIndex];

        const dummy = document.getElementById('dummy') || document.createElement('img');
        dummy.id = 'dummy';
        dummy.onload = function(){
          busy = false;
          const alreadyExisted = !!document.querySelector(`.anime-${i}`);
          const existingEl = document.querySelector(`.anime-${i}`) || document.createElement('div');
          existingEl.className = `anime-box anime-${i}`;
          existingEl.setAttribute('data-i', i);
          if (!alreadyExisted) {
            mainContainer.appendChild(existingEl);
          }
          mainContainer.style.opacity = '';

          if (prevI !== i) {
            existingEl.style.transition = 'none';
            existingEl.style.transform = i > prevI
              ? `translateX(100%)`
              : `translateX(-100%)`;
            existingEl.style.opacity = '0';
            titleWithoutSynopsisRect = undefined;
          }
          const pic = animeData.pics[picIndex];
          const year = animeData.info['Aired:'].match(/\d\d\d\d/)[0];
          const studios = animeData.info['Studios:'];
          const type = animeData.info['Type:'];
          const episodes = animeData.info['Episodes:'];
          const epWord = parseInt(episodes) > 1 ? 'Episodes' : 'Episode';
          if (existingEl.innerHTML === '') {
            existingEl.innerHTML = `
              <div class="anime-info">
                <div class="anime-top-info">
                  <div class="anime-score">
                    <div class="score-${animeData.score}">
                      ${animeData.score}/10
                    </div>
                  </div>
                  <div class="anime-title text">
                    ${animeData.title}
                  </div>
                  ${animeData.altNames['English:'] ? `<div class="anime-eng-title text">
                    ${animeData.altNames['English:']}
                  </div>` : ''}
                  <div class="anime-tags tag-${type.toLowerCase()}">
                    ${type ? `<div>${type}</div>` : ''}
                    ${year ? `<div>${year}</div>` : ''}
                    ${studios ? `<div>${studios}</div>` : ''}
                    ${episodes ? `<div>${episodes} ${epWord}</div>` : ''}
                  </div>
                </div>
                <div class="anime-synopsis text">
                  ${animeData.synopsis.replace(
                    /\[Written by .*\]/,
                    ''
                  ).replace(
                    /\n/g,
                    '<br>'
                  ).replace(
                    /\(Source:.*\)/,
                    ''
                  )}
                </div>
              </div>
              <div class="anime-pic" style="background-image: url('${pic}');">
              </div>
            `;
          } else {
            existingEl.querySelector('.anime-pic').style.backgroundImage = `url('${pic}')`;
          }
          
          const synopsisBox = existingEl.querySelector('.anime-synopsis');
          if (showSynopsis) {
            synopsisBox.style.display = '';
          } else {
            synopsisBox.style.display = 'none';
          }
          const titleNow = existingEl.querySelector('.anime-top-info');
          const rectNow = titleNow.getClientRects()[0];
          if (titleWithoutSynopsisRect) {
            titleNow.style.transition = 'none';
            titleNow.style.transform = `translateY(${titleWithoutSynopsisRect.y - rectNow.y}px)`;
            setTimeout(function(){
              titleNow.style.transition = '';
              titleNow.style.transform = '';
            }, 20);
          }
          titleWithoutSynopsisRect = rectNow;

          setTimeout(function(){
            Array.from(document.querySelectorAll('.anime-box')).forEach(function(el){
              const thisI = el.getAttribute('data-i');
              if (el.classList.contains(`anime-${i}`)) {
                clearTimeout(transitionTimeouts[thisI]);
                existingEl.style.transition = '';
                existingEl.style.transform = 'none';
                existingEl.style.opacity = '1';
                return;
              }
              // el.style.opacity = '0';
              el.style.transform = i > thisI ? 'translateX(-100%)' : 'translateX(100%)';
              transitionTimeouts[thisI] = setTimeout(function(){
                if (i + '' !== thisI + '') {
                  el.remove();
                }
              }, 2000);
            });

            if (prevI !== i) {
              prevI = i;
            }

          }, 50);

        };
        document.body.appendChild(dummy);
        busy = true;
        dummy.src = thisPic;

      }
    </script>
    <script src="animelist.jsonp"></script>
  </body>
</html>