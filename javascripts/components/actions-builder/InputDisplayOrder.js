/*
 * This file is part of the YesWiki Extension advancedsearch.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * This file is part of the YesWiki Extension stats.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
import InputCustomMultiinput from './InputCustomMultiinput.js'

export default {
  mixins: [InputCustomMultiinput],
  methods: {
    parseNewValues(newValues) {
      if (newValues.displayorder) {
        this.elements = []
        let types = newValues.displayorder.split(',')
        let titles = newValues.titles ? newValues.titles.split(',') : []
        for(var i = 0; i < types.length; i++) {
          let isTag = (types[i].length > 4 && types[i].slice(0,4) == "tag:");
          let isForm = !Number.isNaN(Number(types[i])) && parseInt(types[i]) > 0;
          this.elements.push({
              type: ['page','pages'].includes(types[i])
                ? 'pages' 
                : (
                  ['logpage','logspages','logpages','logspage'].includes(types[i])
                  ? 'logpages'
                  : (isTag ? 'tag'
                    : ( isForm ? 'form' : "" )
                  )
                ),
              title: titles.length >= i ? titles[i] : '' ,
              value: isTag
                ? types[i].slice(4) 
                : ( isForm ? parseInt(types[i]) :""),
            }
          );
        }
      }
    },
    getValues() {
      return {
        displayorder: this.elements.map(g => {
          switch (g.type) {
            case 'pages':
              return 'pages'
            case 'logpages':
              return 'logpages';
            case 'tag':
              return `tag:${g.value.replace(',','')}`;
            case 'form':
              return g.value ;
            default:
              return "";
          }
        }).join(','),
        titles: this.elements.map(g => g.title).join(','),
      }
    }
  }
};
