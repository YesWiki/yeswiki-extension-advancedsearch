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

const appAvancedSearchSeeMoreNo = 0;
const appAvancedSearchSeeMoreYes = 1;
const appAvancedSearchSeeMoreToUpdate = 2;

let appParams = {
    components: { SpinnerLoader},
    data() {
        return {
            abortController: null,
            args: {
                separator: "",
                viewtype: "modal",
                displaytext: ""
            },
            hasError: false,
            ready: false,
            results: {},
            searchStack: [],
            searchText: "",
            smallUpdating: false,
            textInput: null,
            titles: {},
            titlesKeys: [],
            updating: true,
            visible: {}
        };
    },
    computed: {
        hasTagCategories(){
            return 'displayorder' in this.args &&
                (Array.isArray(this.args.displayorder) ? this.args.displayorder : String(this.args.displayorder).split(',')).some((category)=>(typeof category == 'string' && category.slice(0,4) == 'tag:'));
        }
    },
    methods: {
        appendNeededLimitsInParams(params,modeSeeMore,modeFirstLong,options){
            let modifiedParams = (typeof params === 'object') ? params : {}
            if (this.args.limit > 0){
                this.updateVisible({},{...options,...{updateSeeMoreStatus:false}})
                if (modifiedParams.limitByCat){
                    if ('page' in this.visible){
                        modifiedParams = this.appendANeededLimitInParams(modifiedParams,'neededByCat[pages]',this.filterResultsAccordingType(this.results,'page'),this.visible.page,modeSeeMore,modeFirstLong)
                    }
                    if ('logpage' in this.visible){
                        modifiedParams = this.appendANeededLimitInParams(modifiedParams,'neededByCat[logpages]',this.filterResultsAccordingType(this.results,'logpage'),this.visible.logpage,modeSeeMore,modeFirstLong)
                    }
                    if ('forms' in this.visible){
                        Object.keys(this.visible.forms).forEach((formId)=>{
                            modifiedParams = this.appendANeededLimitInParams(modifiedParams,`neededByCat[entries][${formId}]`,this.filterResultsAccordingType(this.results,formId),this.visible.forms[formId],modeSeeMore,modeFirstLong)
                        })
                    }
                    if ('tags' in this.visible){
                        Object.keys(this.visible.tags).forEach((tag)=>{
                            modifiedParams = this.appendANeededLimitInParams(modifiedParams,`neededByCat[tags][${tag}]`,this.filterResultsAccordingType(this.results,`tag:${tag}`),this.visible.tags[tag],modeSeeMore,modeFirstLong)
                        })
                    }
                } else {
                    modifiedParams = this.appendANeededLimitInParams(modifiedParams,'neededByCat[noCategory]',Object.values(this.results),this.visible.noCategory,modeSeeMore,modeFirstLong)
                }
            }
            return modifiedParams
        },
        appendANeededLimitInParams(params,name,originData,data,modeSeeMore,modeFirstLong){
            return {
                ...params,
                ...{
                    [name]: (data.seeMore == appAvancedSearchSeeMoreNo || (
                        !modeSeeMore &&
                        data.seeMore == appAvancedSearchSeeMoreYes && originData.length > 0 
                        && (originData.length % this.args.limit) == 0
                    ))
                        ? (modeFirstLong ? 1 : 0)
                        : this.args.limit - (originData.length % this.args.limit)
                }
            }
        },
        extractVisiblesAccordingType(visible,type){
            let extract = {}
            if (type === 'noCategory'){
                extract = (type in visible) ? visible[type] : {}
            } else if (String(Number(type)) == String(type) && type > 0){
                extract = ('forms' in visible && type in visible.forms) ? visible.forms[type] : {}
            } else if (String(type).slice(0,4) == 'tag:') {
                let tag = String(type).slice(4)
                extract = ('tags' in visible && tag in visible.tags) ? visible.tags[tag] : {}
            } else if (['page','logpage'].includes(type)) {
                extract = (type in visible) ? visible[type] : {}
            }
            return extract
        },
        filterEntriesOnResults(results,formIds = []){
            let forms = {}
            Object.keys(results).forEach((key) => {
                let result = results[key]
                if (result.type === 'entry' && (formIds.length == 0 || formIds.includes(result.form))) {
                    if (!(result.form in forms)){
                        forms[result.form] = []
                    }
                    forms[result.form].push(key)
                }
            })
            return {forms}
        },
        filterResultsAccordingType(results, type){
            return Object.keys(results).filter((key) => this.isResultOfType(results[key],type)) ;
        },
        filterTagsOnResults(results,tagsToKeep = []){
            let tags = {}
            Object.keys(results).forEach((key) => {
                let result = results[key]
                if (result.tags.length > 0){
                    if (tagsToKeep.length == 0 ){
                        result.tags.forEach((tag)=>{
                            if (!(tag in tags)){
                                tags[tag] = []
                            }
                            tags[tag].push(key)
                        })
                    } else if (tagsToKeep.some((tag)=>result.tags.includes(tag))){
                        tagsToKeep.filter((tag)=>result.tags.includes(tag)).forEach((tag)=>{
                            if (!(tag in tags)){
                                tags[tag] = []
                            }
                            tags[tag].push(key)
                        })
                    }
                }
            })
            return {tags}
        },
        getAlreadyLoadedIds(type = ''){
            let copiedType = (typeof type === 'string' && type.length > 0) ? type : ''
            let currentResults = (copiedType == 'noCategory' || copiedType.length == 0)
                ? Object.values(this.results)
                : this.filterResultsAccordingType(this.results,copiedType).map((key)=>{return this.results[key]})
            let previousTags = currentResults.map((page)=>page.tag).join(',')
            return previousTags
        },
        getParams(extraParams = {},modeSeeMore = false, modeFirstLong = false,options={}){
            if (typeof extraParams !== 'object'){
                extraParams = {}
            }
            let params = {}
            if (this.args.displaytext){
                params.displaytext = true;
            }
            if ('displayorder' in this.args && this.args.displayorder.length > 0){
                params.categories = Array.isArray(this.args.displayorder) ? this.args.displayorder.join(',') : this.args.displayorder;
                options.categories = params.categories
            }
            if ('onlytags' in this.args && this.args.onlytags.length > 0){
                params.onlytags = this.args.onlytags.join(',');
            }
            params.limit = this.args.limit ?? 0;
            params.limitByCat = (this.args.template == "newtextsearch-by-category.twig");
            params = this.appendNeededLimitsInParams(params,modeSeeMore,modeFirstLong,options)
            for (const key in extraParams) {
                if (key.length > 0){
                    params[key] = extraParams[key];
                }
            }
            return params
        },
        async getSearchViaApi(endUrl = 'search/text-test',signal,params, modePost = false, formObject) {
            this.throwIfAborted(signal)
            let options = {
                signal: signal
            }
            if (modePost){
                let formData = new FormData();
                if (typeof formObject === 'object'){
                    Object.keys(formObject).forEach((key)=>{
                        if (['string','number'].includes(typeof formObject[key])){
                            formData.append(key,formObject[key]);
                        }
                    })
                }
                options.method = 'POST'
                options.body = new URLSearchParams(formData)
                options.headers = (new Headers()).append('Content-Type','application/x-www-form-urlencoded')
            }
            return await fetch(wiki.url(`?api/${endUrl}`,params),options).then((response)=>{
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
                return {
                    data: dataAsArray,
                    extra: data.extra
                }
            })
        },
        getVisiblesAccordingType(results,visible,type){
            let extract = this.extractVisiblesAccordingType(visible,type)
            let ids = 'ids' in extract ? extract.ids : []
            return ids.filter((id)=>id in results).map((id)=>results[id])
        },
        isAbortError(error){
            return typeof error === 'object' && 'name' in error && error.name === 'AbortError'
        },
        isResultOfType(result, type){
            return (result.type == 'entry' && result.form == type)
                || (String(type).slice(0,4) == 'tag:' && result.tags.includes(String(type).slice(4)))
                || result.type == type;
        },
        async moreResults(event) {
            event.preventDefault()
            let button = event.target
            if (button.hasAttribute('disabled')){
                return false
            }
            let type = button.dataset.type;
            let previousTags = this.getAlreadyLoadedIds(type)
            let signal = null
            if (this.abortController === null){
                signal = this.waitForOff()
            } else {
                signal = this.abortController.signal
            }
            let visible = this.extractVisiblesAccordingType(this.visible,type)
            if ('seeMore' in visible){
                visible.seeMore = appAvancedSearchSeeMoreToUpdate
                this.toggleDisplay()
            }
            let params = {
                excludes:previousTags
            }
            if (type != 'noCategory'){
                params.categories = type
            }
            return await this.searchLong(this.searchText,signal,params,{modeSeeMore:true})
                .then(()=>{
                    this.toggleDisplay()
                })
                .catch((error)=>{
                  if (!this.isAbortError(error)){
                      this.hasError = true
                      this.stopUpdating()
                      console.log({error})
                  }
                })
        },
        async processNewText({text,signal}){
            if (text === null){
                // do nothing
                return true
            }
            // reset results
            this.results = {}
            this.visible = {}
            if (text.length == 0){
                return true
            }
            this.updateUrl(text)
            return await this.searchFast(text,signal)
                .catch((error)=>{
                    if (typeof error === 'string' && error.match(/^response not ok ; code : 500.*$/) && this.args.displaytext){
                        this.args.displaytext = false
                        return this.searchFast(text,signal)
                    } else {
                        return Promise.reject(error)
                    }
                })
                .then(({forceTitlesAndRender})=>{
                    let previousTags = this.getAlreadyLoadedIds('')
                    return this.searchLong(text,signal,{excludes:previousTags},{forceTitlesAndRender,modeFirstLong:true})
                })
        },
        async searchFast(text,signal){
            this.throwIfAborted(signal)
            this.updating = true
            let options = {fast:true}
            let params = this.getParams({fast:true},false,false,options)
            return await this.getSearchViaApi(`search/${text}`,signal,params)
                .then(({data,extra})=>{
                    this.updateResults(data,signal)
                    this.updateVisible(extra,options)
                    return {forceTitlesAndRender:('timeLimitReached' in extra && extra.timeLimitReached == true)}
                })
                .finally(()=>{
                    this.updating = false
                })
        },
        async searchLong(text,signal,params = {},rawOptions = {}){
            let copiedParams = typeof params === 'object' ? params : {}
            if ('fast' in copiedParams){
                copiedParams.fast = false
            }
            let options = typeof rawOptions === 'object' ? rawOptions : {}
            const defaultOptions = {
                modeSeeMore: false,
                forceTitlesAndRender: false,
                searchTags: true,
                tagMode: false,
                modeFirstLong: false,
                updateSeeMoreStatus: true
            }
            options = {
                ...defaultOptions,
                ...options
            }
            this.throwIfAborted(signal)
            this.smallUpdating = true
            copiedParams = this.getParams(copiedParams,options.modeSeeMore,options.modeFirstLong,options)
            if ('categories' in copiedParams){
                options.categories = copiedParams.categories
            }
            return await this.getSearchViaApi(`search/${text}`,signal,copiedParams)
                .then(({data,extra})=>{
                    this.updateResults(data,signal)
                    this.updateVisible(extra,options)
                    return {data,extra}
                })
                .then(({data,extra})=>{
                    if (options.forceTitlesAndRender || ('timeLimitReached' in extra && extra.timeLimitReached == true)){
                        return this.updateTitlesIfNeeded(data,signal)
                            .then(()=>{
                                return {data,extra}
                            })
                    } else {
                        return {data,extra}
                    }
                })
                .then(({data,extra})=>{
                    if (this.args.displaytext){
                        return this.updateRenderedIfNeeded(data,params,signal,options.modeFirstLong)
                            .then(()=>{
                                return {data,extra}
                            })
                    } else {
                        return {data,extra}
                    }
                })
                .then(async ({data,extra})=>{
                    const isUpdatingOnlyOneCategory = ('categories' in params &&
                        !params.categories.includes(','))
                    if (!isUpdatingOnlyOneCategory && options.searchTags && this.hasTagCategories){
                        await this.updateTagsIfNeeded(data,params,signal);
                    }
                })
                .finally(()=>{
                    this.smallUpdating = false
                    // force display refresh by toggling ready
                    this.ready = false
                    this.ready = true
                })
        },
        showSeeMoreButton(visible, type, mode = '!=', waitedValue = 'No'){
            let extract = this.extractVisiblesAccordingType(visible,type)
            let waitedVal = (waitedValue === 'No') ? appAvancedSearchSeeMoreNo : (
                (waitedValue === 'Yes') ? appAvancedSearchSeeMoreYes : (
                    appAvancedSearchSeeMoreToUpdate
                )
            )
            return 'seeMore' in extract && (
                (mode === '!=' && extract.seeMore != waitedVal) ||
                (mode != '!=' && extract.seeMore == waitedVal)
            )
        },
        stopUpdating(){
            this.updating = false;
            for (const key in this.categories) {
                this.$set(this.categories,key,{...this.categories[key],...{updating:false}})
            }
        },
        throwIfAborted(signal = null) {
            let currentSignal = signal
            if (currentSignal === null){
                if (this.abortController === null){
                    throw 'AbortController not initialized'
                }
                currentSignal = this.abortController.signal
            }
            if (currentSignal.aborted){
                throw currentSignal.reason
            }
        },
        toggleDisplay(){
            this.ready = false
            this.ready = true
        },
        async updateObjectIfNeeded(data,key,route,signal){
            if (Array.isArray(data.results)){
                let entriesWithoutKey = data.results.filter((page)=>(!(key in page)||page[key].length == 0));
                if (entriesWithoutKey.length > 0){
                    let tags = entriesWithoutKey.map((page)=>page.tag);
                    let formObject = {}
                    tags.forEach((tag,idx)=>{
                        formObject[`tags[${idx}]`] = tag;
                    });
                    await this.getSearchViaApi(`api/search/${route}/`,signal,{},true,formObject)
                        .then(({data})=>{
                            this.updateResults(data.results,signal)
                            // toggle ready
                            this.toggleDisplay()
                        })
                }
            }
        },
        updateResults(data, signal){
            this.throwIfAborted(signal)
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
        },
        async updateTagsIfNeeded(data,params,signal){
            await this.updateObjectIfNeeded(data,'tags','getTags',signal)
                .then(()=>{
                    let tagsCat = (Array.isArray(this.args.displayorder) 
                        ? this.args.displayorder 
                        : String(this.args.displayorder).split(',')).filter((category)=>(typeof category == 'string' && category.slice(0,4) == 'tag:'));
                    const processTagCat = async (tagCat) => {
                        await this.searchLong(this.searchText,signal,{
                            ...params,
                            ...{
                                forceDisplay: true,
                                categories: tagCat
                            }
                        },
                        {
                            searchTags:false,
                            tagMode: true
                        })
                    }
                    const processTagsCat = async (tagsCat) => {
                        for (let index = 0; index < tagsCat.length; index++) {
                            const tagCat = tagsCat[index];
                            await processTagCat(tagCat)
                        }
                    }
                    return processTagsCat(tagsCat)
                })
        },
        async updateTitlesIfNeeded (data, signal){
            await this.updateObjectIfNeeded(data,'title','getTitles',signal)
        },
        updateVisible(extra = {},rawOptions = {}) {  
            let options = typeof rawOptions === 'object' ? rawOptions : {}
            const defaultOptions = {
                fast: false,
                tagMode: false,
                updateSeeMoreStatus: true,
                modeFirstLong: false,
                categories: ''
            }
            options = {
                ...defaultOptions,
                ...options
            }
            if (this.args.template == "newtextsearch-by-category.twig"){
                if (this.args.displayorder.length == 0){
                    
                    this.updateVisibleIds('page',this.args.limit,extra,options)
                    this.updateVisibleEntries(this.args.limit,[],extra,options)
                } else {
                    if (this.args.displayorder.includes('page')){
                        this.updateVisibleIds('page',this.args.limit,extra,options)
                    }
                    if (this.args.displayorder.includes('logpage')){
                        this.updateVisibleIds('logpage',this.args.limit,extra,options)
                    }
                    let formIds = []
                    let tags = []
                    this.args.displayorder.forEach((name)=>{
                        if (String(name)==String(Number(name))){
                            formIds.push(String(name))
                        } else if (String(name).slice(0,4)=='tag:'){
                            tags.push(String(name).slice(4))
                        }
                    })
                    if (formIds.length > 0){
                        this.updateVisibleEntries(this.args.limit,formIds,extra,options)
                    }
                    if (tags.length > 0){
                        this.updateVisibleTags(this.args.limit,tags,extra,options)
                    }
                }

            } else {
                this.updateVisibleIds('noCategory',this.args.limit,extra,options)
            }
        },
        updateVisibleEntries(limit,formIds = [],extra={},options = {}){
            let {forms} = this.filterEntriesOnResults(this.results,formIds)
            for (const key in forms) {
                if (!('forms' in this.visible)){
                    this.visible.forms = {}
                }
                if (!(key in this.visible.forms)){
                    this.visible.forms[key] = {
                        seeMore: appAvancedSearchSeeMoreToUpdate,
                        ids: []
                    }
                }
                this.udpateVisibleItem(key,extra,limit,forms[key],this.visible.forms[key],(limitsReached,modeFirstLong)=>('forms' in limitsReached && key in limitsReached.forms)? limitsReached.forms[key] : (modeFirstLong ? 'no' : ''),options)
            }
        },
        udpateVisibleItem(cat,extra,limit,ids,baseObj,extractLimit,options){
            let nbToKeep = (limit === 0) ? 0 : Math.min(Math.max(Math.floor(ids.length / limit),1)*limit,ids.length)
            if (options.updateSeeMoreStatus && limit > 0 && (options.categories.length === 0 || options.categories.includes(cat))){
                if (
                    !(options.fast || 'limitsReached' in extra) ||
                    (
                        'limitsReached' in extra && 
                        typeof extractLimit === 'function' && 
                        extractLimit(extra.limitsReached,options.modeFirstLong) == 'no'
                    )){
                    baseObj.seeMore = appAvancedSearchSeeMoreNo
                } else {
                    let extract = ('limitsReached' in extra && typeof extractLimit === 'function') ? extractLimit(extra.limitsReached,options.modeFirstLong): ''
                    if (extract == 'yes' || (Math.floor(nbToKeep / limit)*limit == nbToKeep && ids.length > nbToKeep)) {
                        baseObj.seeMore = appAvancedSearchSeeMoreYes
                    } else if ((!options.fast && extract == 'no') || options.tagMode) {
                        baseObj.seeMore = appAvancedSearchSeeMoreNo
                    }
                }
            }
            if (baseObj.seeMore === appAvancedSearchSeeMoreNo){
                baseObj.ids = ids
            } else {
                baseObj.ids = ids.slice(0,nbToKeep)
            }
        },
        updateVisibleIds(category,limit,extra = {},options = {}){
            let ids = (category == 'noCategory') ? Object.keys(this.results) : this.filterResultsAccordingType(this.results,category)
            if (!(category in this.visible)){
                this.visible[category] = {
                    seeMore: appAvancedSearchSeeMoreToUpdate,
                    ids: []
                }
            }
            this.udpateVisibleItem((category == 'noCategory') ? '' : category,extra,limit,ids,this.visible[category],(limitsReached,modeFirstLong)=>limitsReached[category] || (modeFirstLong ? 'no' : ''),options)
        },
        updateVisibleTags(limit,tagsToKeep = [],extra = {},options = {}){
            let {tags} = this.filterTagsOnResults(this.results,tagsToKeep)
            for (const key in tags) {
                if (!('tags' in this.visible)){
                    this.visible.tags = {}
                }
                if (!(key in this.visible.tags)){
                    this.visible.tags[key] = {
                        seeMore: appAvancedSearchSeeMoreToUpdate,
                        ids: []
                    }
                }
                this.udpateVisibleItem(`tag:${key}`,extra,limit,tags[key],this.visible.tags[key],(limitsReached,modeFirstLong)=>('tags' in limitsReached && key in limitsReached.tags)? limitsReached.tags[key] : (modeFirstLong ? 'no' : ''),options)
            }
        },
        async updateRenderedIfNeeded(data,params,signal,modeFirstLong){
            if (modeFirstLong || Array.isArray(data.results)){
                let base = modeFirstLong ? Object.keys(this.results).map((id)=>this.results[id]) : data.results
                let entriesWithoutPreRendered = base.filter((page)=>(!('preRendered' in page)||page.preRendered.length == 0));
                if (entriesWithoutPreRendered.length > 0){
                    let copiedParams = {...params}
                    if ('excludes' in copiedParams){
                        delete copiedParams.excludes
                    }
                    copiedParams.forceDisplay = true
                    entriesWithoutPreRendered.forEach((e,idx)=>{copiedParams[`keepOnlyTags[${idx}]`]=e.tag})
                    await this.searchLong(this.searchText,signal,copiedParams,{updateSeeMoreStatus:false})
                }
            }
        },
        updateSearchText() {
            this.searchText = $(this.textInput).val()
        },
        updateUrl(searchText){
            let url = window.location.toString();
            let rewriteMode = (
                wiki &&
                typeof wiki.baseUrl === "string" &&
                !wiki.baseUrl.includes("?")
                );
            let newUrl = url;
            if (url.includes("&phrase=")){
                let urlSplitted = url.split("&phrase=")
                let textRaw = urlSplitted[1]
                let textRawSplitted = textRaw.split("&")
                let oldText = textRawSplitted[0]
                newUrl = url.replace(`&phrase=${oldText}`,`&phrase=${encodeURIComponent(searchText)}`)
            } else if (rewriteMode && url.includes("?phrase=")) {
                let urlSplitted = url.split("?phrase=")
                let textRaw = urlSplitted[1]
                let textRawSplitted = textRaw.split("&")
                let oldText = textRawSplitted[0]
                newUrl = url.replace(`?phrase=${oldText}`,`?phrase=${encodeURIComponent(searchText)}`)
            } else {
                newUrl = url.includes(rewriteMode ? '?' : '&') 
                    ? `${url}&phrase=${encodeURIComponent(searchText)}` 
                    : (
                        rewriteMode
                        ? `${url}?phrase=${encodeURIComponent(searchText)}`
                        : `${url}&phrase=${encodeURIComponent(searchText)}`
                    );
            }
            history.pushState({ filter: true }, null, newUrl)
        },
        async waitForOff(reason = null){
            this.hasError = false
            let errorTriggerred = false
            let err = null
            try {
                if (this.abortController !== null){
                    if (typeof reason === 'string') {
                        this.abortController.abort(reason)
                    } else {
                        this.abortController.abort()
                    }
                } else {
                    this.abortController = new AbortController()
                }
            } catch (error) {
                errorTriggerred = true
                err = error
            } finally {
                let nextSearch = null
                if (this.searchStack.length > 0){
                    nextSearch = this.searchStack.pop()
                    this.searchStack = [] // reset stack
                }
                if (errorTriggerred){
                    throw err
                }
                if (this.abortController.signal.aborted){
                    this.abortController = new AbortController()
                }
                return {text:nextSearch,signal: this.abortController.signal}
            }
        }
    },
    watch: {
        searchText(newValue,oldValue){
            if (newValue != oldValue){
                this.searchStack.push(newValue)
                this.waitForOff('New text to search')
                  .then(this.processNewText)
                  .catch((error)=>{
                    if (!this.isAbortError(error)){
                        this.hasError = true
                        this.stopUpdating()
                        console.log({error})
                    }
                  })
            } else {
                this.stopUpdating()
            }
        }
    },
    mounted(){
        $(isVueJS3 ? this.$el.parentNode : this.$el).on('dblclick',function(e) {
          return false;
        });
        this.abortController = new AbortController()
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
            $(this.textInput).parent().find('button[type=submit]').on('click',(event)=>{
                event.preventDefault();
                this.updateSearchText();
            });
        } else {
            this.searchText = $(isVueJS3 ? this.$el.parentNode : this.$el).data("initialsearchtext");
        }
        if (this.searchText.length == 0){
            this.updating = false;
        }
        this.ready = true
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
