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
import SpinnerLoader from '../../bazar/presentation/javascripts/components/SpinnerLoader.js'

let rootsElements = ['div.search-results'];
let isVueJS3 = (typeof Vue.createApp == "function");

let appParams = {
    components: { SpinnerLoader},
    data: function() {
        return {
            abortController: null,
            args: {
                separator: "",
                viewtype: "modal",
                displaytext: ""
            },            
            doMContentLoaded: false,
            doNotShowMoreFor: [],
            ready: false,
            results: {},
            searchText: "",
            textInput: null,
            titles: {},
            titlesKeys: [],
            updating: true
        };
    },
    computed: {
        hasTagCategories: function(){
            return 'displayorder' in this.args &&
                (Array.isArray(this.args.displayorder) ? this.args.displayorder : String(this.args.displayorder).split(',')).some((category)=>(typeof category == 'string' && category.slice(0,4) == 'tag:'));
        }
    },
    methods: {
        getSignalFromAbortController: function (stop = true){
            if (stop){
            this.stopCurrentSearch();
            }
            if (this.abortController === null){
            this.abortController = new AbortController();
            }
            return this.abortController.signal;
        },
        updateSearchText: function () {
            this.searchText = $(this.textInput).val();
        },
        filterResultsAccordingType: function(results, type){
            return Object.keys(results).filter((key) => this.isResultOfType(results[key],type)) ;
        },
        isResultOfType: function(result, type){
            return (result.type == 'entry' && result.form == type)
                || (String(type).slice(0,4) == 'tag:' && result.tags.includes(String(type).slice(4)))
                || result.type == type;
        },
        updateUrl: function(searchText){
            let url = window.location.toString();
            let rewriteMode = (
                wiki &&
                typeof wiki.baseUrl == "string" &&
                !wiki.baseUrl.includes("?")
                );
            let newUrl = url;
            if (url.includes("&phrase=")){
                let urlSplitted = url.split("&phrase=");
                let textRaw = urlSplitted[1];
                let textRawSplitted = textRaw.split("&");
                let oldText = textRawSplitted[0];
                newUrl = url.replace(`&phrase=${oldText}`,`&phrase=${encodeURIComponent(searchText)}`);
            } else if (rewriteMode && url.includes("?phrase=")) {
                let urlSplitted = url.split("?phrase=");
                let textRaw = urlSplitted[1];
                let textRawSplitted = textRaw.split("&");
                let oldText = textRawSplitted[0];
                newUrl = url.replace(`?phrase=${oldText}`,`?phrase=${encodeURIComponent(searchText)}`);
            } else {
                newUrl = url.includes(rewriteMode ? '?' : '&') 
                    ? `${url}&phrase=${encodeURIComponent(searchText)}` 
                    : (
                        rewriteMode
                        ? `${url}?phrase=${encodeURIComponent(searchText)}`
                        : `${url}&phrase=${encodeURIComponent(searchText)}`
                    );
            }
            history.pushState({ filter: true }, null, newUrl);
        },
        showSeeMoreButton: function(results, type){
            if (type == ''){
                return (Object.keys(results).length > 0 && Object.keys(results).length % this.args.limit == 0);
            } else if (this.doNotShowMoreFor.includes(type)) {
                return false;
            } else {
                return (this.filterResultsAccordingType(results, type).length % this.args.limit == 0);
            }
        },
        moreResults: function(event) {
            event.preventDefault();
            let button = event.target;
            let type = button.dataset.type;
            let currentResults = (type == '')
                ? Object.values(this.results)
                : this.filterResultsAccordingType(this.results,type).map((key)=>{return this.results[key];})
            let previousTags = currentResults.map((page)=>page.tag).join(',');
            this.searchTextFromApi({excludes:previousTags,categories:type});
        },
        searchTextFromApi: function(extraParams = {},stop = true,searchTags = true){
            if (stop){
            this.stopCurrentSearch();
            }
            if (Object.keys(extraParams).length == 0){
                // clear results
                this.results = {};
            }
            this.updating = true;
            let params = {};
            if (this.args.displaytext){
                params.displaytext = true;
            }
            if (this.args.limit > 0){
                params.limit = this.args.limit;
            }
            if (this.args.template == "newtextsearch-by-category.twig"){
                params.limitByCat = true;
            }
            if (this.args.hasOwnProperty('displayorder') && this.args.displayorder.length > 0){
                params.categories = Array.isArray(this.args.displayorder) ? this.args.displayorder.join(',') : this.args.displayorder;
            }
            if (this.args.hasOwnProperty('onlytags') && this.args.onlytags.length > 0){
                params.onlytags = this.args.onlytags.join(',');
            }
            for (const key in extraParams) {
                if (key.length > 0){
                    params[key] = extraParams[key];
                }
            }
            fetch(wiki.url(`api/search/${this.searchText}`,params),{signal:this.getSignalFromAbortController(stop)})
                .then((response)=>{
                    if (!response.ok){
                        throw `response not ok ; code : ${response.status} (${response.statusText})`;
                    }
                    return response.json();
                })
                .then((data)=>{
                    if (!('results' in data) || !('extra' in data)){
                        throw 'Received data badly formatted !';
                    }
                    let dataAsArray =
                        (Array.isArray(data.results))
                        ? data.results
                        : ((typeof data.results == "object")
                            ? Object.values(data.results)
                            : []
                        );
                    if ('categories' in extraParams &&
                        !extraParams.categories.includes(',') &&
                        dataAsArray.length == 0 &&
                        !this.doNotShowMoreFor.includes(extraParams.categories)
                        ){
                        this.doNotShowMoreFor.push(extraParams.categories);
                    }
                    this.updateResults(dataAsArray);
                    this.updating = false;
                    if (data.extra.timeLimitReached){
                        this.updateTitlesIfNeeded(data);
                        if (this.args.displaytext){
                            this.updateRenderedIfNeeded(data,extraParams);
                        }
                    }
                    if (searchTags && this.hasTagCategories){
                        this.updateTagsIfNeeded(data,extraParams);
                    }
                })
                // do nothing on error
                .catch((e)=>{
                    if (e.name !== 'AbortError'){
                        this.updating = false; // do not change updating if aborted
                        throw e;
                    }
                })
                .finally(()=>{
                    this.ready = true;
                })
                },
        stopCurrentSearch: function(){
            if (this.abortController !== null){
                try {
                    this.abortController.abort();
                } catch (error) {
                    console.log(`Error when aborting : ${error.toString()}`)
                }
            }
            this.abortController = null;
        },
        updateResults: function(data){
            if (this.abortController !== null){
                data.forEach((value)=>{
                    if ('tag' in value && value.tag !=''){
                        if (value.tag in this.results){
                            if ('title' in value && value.title.length > 0){
                                if (!('title' in this.results[value.tag]) || 
                                    this.results[value.tag].title.length == 0){
                                    this.results[value.tag].title = value.title;
                                }
                            }
                            if ('tags' in value && value.tags.length > 0){
                                if (!('tags' in this.results[value.tag]) || 
                                    this.results[value.tag].tags.length == 0){
                                    this.results[value.tag].tags = value.tags;
                                }
                            }
                            if ('preRendered' in value && value.preRendered.length > 0){
                                if (!('preRendered' in this.results[value.tag]) || 
                                    this.results[value.tag].preRendered.length == 0){
                                    this.results[value.tag].preRendered = value.preRendered;
                                }
                            }
                        } else {
                            this.results[value.tag] = value;
                        }
    
                    }
                });
            }
        },
        updateRenderedIfNeeded: function(data,extraParams){
            if (Array.isArray(data.results)){
                let entriesWithoutPreRendered = data.results.filter((page)=>(!('preRendered' in page)||page.preRendered.length == 0));
                if (entriesWithoutPreRendered.length > 0){
                    this.searchTextFromApi({
                        ...extraParams,
                        ...{
                            forceDisplay: true
                        }
                    },false);
                }
            }
        },
        updateObjectIfNeeded: function(data,key,route){
            if (Array.isArray(data.results)){
                let entriesWithoutKey = data.results.filter((page)=>(!(key in page)||page[key].length == 0));
                if (entriesWithoutKey.length > 0){
                    let tags = entriesWithoutKey.map((page)=>page.tag);
                    let formData = new FormData();
                    tags.forEach((tag,idx)=>{
                        formData.append(`tags[${idx}]`,tag);
                    });
                    fetch(wiki.url(`api/search/${route}/`),{
                        method: 'POST',
                        body: new URLSearchParams(formData),
                        headers : (new Headers()).append('Content-Type','application/x-www-form-urlencoded'),
                        signal:this.getSignalFromAbortController(false)
                    })
                    .then((response)=>{
                        if (!response.ok){
                            throw `response not ok ; code : ${response.status} (${response.statusText})`;
                        }
                        return response.json();
                    })
                    .then((data)=>{
                        if (!('results' in data) || !Array.isArray(data.results) || !('extra' in data)){
                            throw 'Received data badly formatted !';
                        }
                        this.updateResults(data.results);
                        // toggle ready
                        this.ready = false;
                        this.ready = true;
                    })
                    // do nothing on error
                    .catch((e)=>{
                        if (e.name !== 'AbortError'){
                            this.updating = false; // do not change updating if aborted
                            throw e;
                        }
                    })
                }
            }
        },
        updateTagsIfNeeded: function(data,extraParams){
            this.updateObjectIfNeeded(data,'tags','getTags');
            let tagsCat = (Array.isArray(this.args.displayorder) ? this.args.displayorder : String(this.args.displayorder).split(',')).filter((category)=>(typeof category == 'string' && category.slice(0,4) == 'tag:'));
            if (tagsCat.length > 0){
                tagsCat.forEach((tagCat)=>{
                    this.searchTextFromApi({
                        ...extraParams,
                        ...{
                            forceDisplay: true,
                            categories: tagCat
                        }
                    },false,false);
                });
            }
        },
        updateTitlesIfNeeded: function(data){
            this.updateObjectIfNeeded(data,'title','getTitles');
        }
    },
    watch: {
        searchText: function (newValue,oldValue){
            this.updateUrl(newValue);
            if (newValue != oldValue){
                if (newValue.length == 0){
                    this.results = {};
                    this.updating = false;
                } else {
                    // reset results
                    this.results = {};
                    this.searchTextFromApi();
                }
            } else {
                this.updating = false;
            }
        }
    },
    mounted(){
        $(isVueJS3 ? this.$el.parentNode : this.$el).on('dblclick',function(e) {
          return false;
        });
        document.addEventListener('DOMContentLoaded', () => {
            this.doMContentLoaded = true;
        });
        this.args = $(isVueJS3 ? this.$el.parentNode : this.$el).data("args");
        this.titles = $(isVueJS3 ? this.$el.parentNode : this.$el).data("titles");
        this.titlesKeys = $(isVueJS3 ? this.$el.parentNode : this.$el).data("titles-keys");
        if (!this.args.hasOwnProperty('viewtype')){
            this.args.viewtype = "modal";
        }
        if (!this.args.hasOwnProperty('separator')){
            this.args.separator = "";
        }
        if (this.args.separator.length > 0) {
            this.args.displaytext = false;
        }
        if (!this.args.hasOwnProperty('displaytext') || !(this.args.displaytext !== true || this.args.displaytext !== false)){
            this.args.displaytext = !this.args.hasOwnProperty('template') || this.args.template == "newtextsearch.twig";
        }
        let forms = $('.search-form');
        if (forms != undefined && forms.length > 0){
            let form = $(forms).first();
            let textInput = $(form).find('input[type=text]:first');
            if (textInput != undefined && textInput.length > 0){
                this.textInput = textInput;
            }
        }
        if (this.textInput && this.textInput != undefined) {
            this.searchText = this.textInput.val();
            $(this.textInput).on('change',()=>this.updateSearchText());
            $(this.textInput).parent().find('input[type=submit]').on('click',(event)=>{
                event.preventDefault();
                this.updateSearchText();
            });
        } else {
            this.searchText = $(isVueJS3 ? this.$el.parentNode : this.$el).data("initialsearchtext");
        }
        if (this.searchText.length == 0){
            this.updating = false;
        }
    }
};

if (isVueJS3){
    let app = Vue.createApp(appParams);
    app.config.globalProperties.wiki = wiki;
    app.config.globalProperties._t = _t;
    rootsElements.forEach(elem => {
        app.mount(elem);
    });
} else {
    Vue.prototype.wiki = wiki;
    Vue.prototype._t = _t;
    rootsElements.forEach(elem => {
        new Vue({
            ...{el:elem},
            ...appParams
        });
    });
}