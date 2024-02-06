import { createRoot } from "react-dom/client";
import React from "react";

import * as uuid from 'uuid';

/**
 * Components
 */
import Form from "./Form";
import Table from "./Table";
import CardButton from "./CardButton";
import Title from "./Title";
import Breadcrumbs from "./Breadcrumbs";
import Card from "./Card";
import Button from "./Button";
import Modal from "./Modal";
import FormButton from "./FormButton";
import FormCardButton from "./FormCardButton";
import Calendar from "./Calendar";
import SwalButton from "./SwalButton";
import View from "./View";

/**
* Examples
*/
import Example from "./Example";
import ExampleModelHover from "./Examples/ModelHover";

/**
* Initialize ADIOS components
*/
const initializeComponents = [
  'breadcrumbs',
  'card-button',
  'card',
  'calendar',
  'form',
  'table',
  'title',
  'button',
  'modal',
  'form-button',
  'form-card-button',
  'swal-button',
  'view',
  
  // Examples
  'example',
  'example-model-hover'
];

/**
 * Define attributes which will not removed
 */
const attributesToSkip = [
  'onclick'
];

/**
* Get specific ADIOS component with destructed params 
*/
const getComponent = (componentName: string, props: Object) => {

  // Check if uid exists or create custom
  if (props['uid'] == undefined) {
    props['uid'] = uuid.v4();
  }

  switch (componentName) {
    //@ts-ignore
    case 'form': return <Form {...props} />;
    //@ts-ignore
    case 'table': return <Table {...props} />;
    //@ts-ignore
    case 'card-button': return <CardButton {...props} />;
    //@ts-ignore
    case 'card': return <Card {...props} />;
    //@ts-ignore
    case 'calendar': return <Calendar {...props} />;
    //@ts-ignore
    case 'title': return <Title {...props} />;
    //@ts-ignore
    case 'breadcrumbs': return <Breadcrumbs {...props} />;
    //@ts-ignore
    case 'button': return <Button {...props} />;
    //@ts-ignore
    case 'modal': return <Modal {...props} ></Modal>;
    //@ts-ignore
    case 'form-button': return <FormButton {...props} />;
    //@ts-ignore
    case 'form-card-button': return <FormCardButton {...props} />;
    //@ts-ignore
    case 'swal-button': return <SwalButton {...props} />;
    //@ts-ignore
    case 'view': return <View {...props} />;

    // Examples
    case 'example': return <Example {...props} />;
    case 'example-model-hover': return <ExampleModelHover {...props} />;
    default: return <b style={{color: 'red'}}>Component {componentName} doesn't exist</b>; 
  }
};

/**
 * Render React component (create HTML tag root and render) 
 */
const renderComponent = (specificHtmlElement: string, component: string) => {
  const allComponentsWithSameId = document.querySelectorAll(
    specificHtmlElement + ' adios-' + component);

  allComponentsWithSameId.forEach((element, _index) => {
    let componentProps: Object = {};

    // Find attribute and also delete him using [0] index
    let i: number = 0
    while (element.attributes.length > i) {
      let attributeName: string = element.attributes[i].name.replace(/-([a-z])/g, (_: any, letter: string) => letter.toUpperCase());
      let attributeValue: any = element.attributes[i].value;

      if (isValidJSON(attributeValue)) {
        let attributeValues: Object|Array<any> = JSON.parse(attributeValue);
        if (!Array.isArray(attributeValues)) {
          attributeValue = {};

          attributeValue  = Object.keys(attributeValues).reduce(function(result, key) {
            result[key] = getValidatedAttributeValue(key, attributeValues[key]);
            return result;
          }, {});
        } else {
          attributeValue = attributeValues;
        }
      }

      componentProps[attributeName] = getValidatedAttributeValue(attributeName, attributeValue); 

      if (attributesToSkip.includes(attributeName)) {
        i++;
        continue;
      }

      // Remove attributes from HTML DOM
      element.removeAttribute(element.attributes[i].name);
    }

    let componentBuildElement = createRoot(element);
    componentBuildElement.render(getComponent(component, componentProps));
  });
}

window.renderComponents = (specificHtmlElement: string) => {
  initializeComponents.map(item => renderComponent(specificHtmlElement, item))
}

window.renderComponents('body');

function isValidJSON(jsonString: string) {
  try {
    let tmp = JSON.parse(jsonString);

    if (tmp && typeof tmp === "object") {
      return true;
    } else {
      return false;
    }
  } catch (error) {
    return false;
  }
}

/**
 * Validate attribute value
 * E.g. if string contains Callback create frunction from string
 */
function getValidatedAttributeValue(attributeName: string, attributeValue: any): Function|any {
  return attributeName.toLowerCase().includes('callback') ? new Function(attributeValue) : attributeValue;
}

/**
 * Define global functions
 */
declare global {
  interface Window {
    renderComponents: (specificHtmlElement: string) => void;
    getComponent: (componentName: string, params: Object) => React.JSX.Element;
    _APP_URL: string;
  }
}

window.getComponent = getComponent;






ADIOS.registerReactElement('Form', Form);
ADIOS.registerReactElement('Table', Table);
ADIOS.registerReactElement('CardButton', CardButton);
ADIOS.registerReactElement('Title', Title);
ADIOS.registerReactElement('Breadcrumbs', Breadcrumbs);
ADIOS.registerReactElement('Card', Card);
ADIOS.registerReactElement('Button', Button);
ADIOS.registerReactElement('Modal', Modal);
ADIOS.registerReactElement('FormButton', FormButton);
ADIOS.registerReactElement('FormCardButton', FormCardButton);
