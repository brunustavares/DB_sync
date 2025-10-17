/**
 * _DBsync_
 * PHP app for synchronization of registrations and grades, between Moodle
 * and WISEflow databases (MySQL) and academic management system (Oracle).
 * (developed for UAb - Universidade Aberta)
 *
 * @package    _DBsync_
 * @category   app
 * @author     Bruno Tavares <brunustavares@gmail.com>
 * @link       https://www.linkedin.com/in/brunomastavares/
 * @copyright  Copyright (C) 2024-2025 Bruno Tavares
 * @license    GNU General Public License v3 or later
 *             https://www.gnu.org/licenses/gpl-3.0.html
 * @version    2025072811
 * @date       2024-04-04
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

function add_title(container, idx, text, delay, color){
    var newH2 = document.createElement("h2");
    var newID = "title" + idx;

    newH2.textContent = text;
    newH2.setAttribute("class", "title_lbl");
    newH2.setAttribute("id", newID);

    document.getElementById(container).appendChild(newH2);
    setTimeout(function() {
        newH2.style.color = color;
        newH2.style.opacity = "1";
    }, delay);

    // window.scroll({top: document.body.scrollHeight, behavior: "smooth" });
}

function get_result(idx, max_val, delay){
    setTimeout(function() {
        var pbarID = "pbar" + idx;
        var bar = new ProgressBar.Line(`#${pbarID}`, {
          strokeWidth: 5,
          easing: 'easeInOut',
          duration: max_val,
          color: '#00C1AD',
          trailColor: '#eee',
          trailWidth: 1,
          svgStyle: {width: '100%', height: '100%'},
          text: {
                 style: {
                 color: '#999',
                 position: 'absolute',
                 right: '0',
                 top: '15px',
                 padding: 0,
                 margin: 0,
                 transform: null
          },
          autoStyleContainer: false
          },
          from: {color: '#FFEA82'},
          to: {color: '#ED6A5A'},
          step: (state, bar) => {
                                 bar.setText(Math.round(bar.value() * 100) + ' %');
          }
        });
      
        bar.animate(1.0);
    }, delay);

    // window.scroll({top: document.body.scrollHeight, behavior: "smooth" });
}

function show_result(container, idx, text, delay){
    var newP = document.createElement("p");
    var newID = "result" + idx;
    var pbarID = "pbar" + idx;
    var pbar = document.getElementById(pbarID);

    newP.setAttribute("class", "result_lbl");
    newP.setAttribute("id", newID);
    newP.innerHTML = text.replace(/\s\|\s/g, '<br>');

    document.getElementById(container).appendChild(newP);

    setTimeout(function() {
        setTimeout(function() {
            pbar.style.opacity = "0";
            setTimeout(function() {
                pbar.style.display = "none";
                newP.style.display = "block";
                setTimeout(function() {
                    newP.style.opacity = "1";
                    insert_break(container, idx);
                }, 500);
            }, 500);
        }, 500);
    }, delay);

    window.scroll({top: document.body.scrollHeight, behavior: "smooth" });
}

function insert_break(container, idx){
    var newHR = document.createElement("hr");
    var newID = "break" + idx;

    newHR.setAttribute("class", "break");
    newHR.setAttribute("id", newID);
    
    document.getElementById(container).appendChild(newHR);

    setTimeout(function() {
        newHR.style.opacity = "1";
    }, 500);

    window.scroll({top: document.body.scrollHeight, behavior: "smooth" });
}

function the_end(delay) {
    setTimeout(function() {
        var div = document.createElement('div');
        var iframe = document.createElement('iframe');
        var br01 = document.createElement('br');
        var br02 = document.createElement('br');
        var br03 = document.createElement('br');
        var br04 = document.createElement('br');
        var br05 = document.createElement('br');

        div.setAttribute("class", "the_end");
        div.setAttribute("id", "the_end");

        iframe.setAttribute("class", "the_end");

        iframe.title = 'That\'s all folks!';
        iframe.src = 'https://www.youtube.com/embed/b9434BoGkNQ?si=CNyTQwfpf4TJof1h&autoplay=1&mute=0';
        iframe.width = '350';
        iframe.height = '196';
        // iframe.frameborder = 0;
        iframe.allow = 'autoplay; picture-in-picture; encrypted-media';
        // iframe.allowfullscreen = 1;
        iframe.referrerpolicy = 'strict-origin-when-cross-origin';
        iframe.autoplay = 1;
        iframe.mute = 0;

        div.appendChild(br01);
        div.appendChild(br02);
        div.appendChild(iframe);
        div.appendChild(br03);
        div.appendChild(br04);
        div.appendChild(br05);

        document.body.appendChild(div);

        window.scroll({top: document.body.scrollHeight, behavior: "smooth" });
    }, delay);
}
