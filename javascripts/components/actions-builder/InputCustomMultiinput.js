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
import InputHelper from '../../../../aceditor/presentation/javascripts/components/InputHelper.js'
import InputHidden from '../../../../aceditor/presentation/javascripts/components/InputHidden.js'
import InputText from '../../../../aceditor/presentation/javascripts/components/InputText.js'
import InputCheckbox from '../../../../aceditor/presentation/javascripts/components/InputCheckbox.js'
import InputList from '../../../../aceditor/presentation/javascripts/components/InputList.js'
import InputIcon from '../../../../aceditor/presentation/javascripts/components/InputIcon.js'
import InputColor from '../../../../aceditor/presentation/javascripts/components/InputColor.js'
import InputFormField from '../../../../aceditor/presentation/javascripts/components/InputFormField.js'
import InputValueForDisplayOrder from './InputValueForDisplayOrder.js'

export default {
  props: [ 'name', 'value', 'config', 'selectedForm', 'values' ],
  components: { InputText, InputCheckbox, InputList, InputIcon, InputColor, InputFormField, InputHidden, InputValueForDisplayOrder },
  mixins: [ InputHelper ],
  data() {
    return {
      elements: []
    }
  },
  computed: {
    propertiesIds() {
      return Object.keys(this.config.subproperties)
    }
  },
  mounted() {
    this.parseNewValues(this.values)
  },
  methods: {
    addElement() {
      let element = {}
      this.propertiesIds.forEach(id => element[id] = '')
      this.elements.push(element)
    },
    removeElement(group) {
      this.elements = this.elements.filter(el => el[this.propertiesIds[0]] != group[this.propertiesIds[0]])
    },
    resetValues() {
      this.elements = []
    },
    parseNewValues(newValues) {
      console.warn("parseNewValues Method should be implement in sub component")
    },
    getValues() {
      console.warn("getValues Method should be implement in sub component")
    }
  },
  watch: {
    elements: {
      handler(val) { this.$emit('input', val) },
      deep: true
    },
  },
  template: `
    <div class="multi-input-container" :class="name">
      <div class="inline-form" v-for="(element,elementKey) in elements">
        <template v-for="(property, propName) in config.subproperties">
          <component :is="componentIdFrom(property)" v-model="element[propName]"
                     v-show="checkVisibility(property)" :name="propName" :values="values"
                     :config="property" :selected-form="selectedForm" :element-key="elementKey">
          </component>
        </template>
        <!-- Remove Button -->
        <div class="form-group btn-close-container">
          <button class="btn btn-default btn-icon" @click="removeElement(element)">
            <i class="btn-remove-group fa fa-times"></i>
          </button>
        </div>
      </div>
      <!-- Add Button -->
      <button @click="addElement" class="btn btn-info btn-icon btn-add-element">
        <i class="fa fa-plus"></i>
        <span v-if="config['btn-label-add']">{{ config['btn-label-add'] }}</span>
      </button>
      <input-hint :config="config"></input-hint>
    </div>`
}
