import React, { Component, JSXElementConstructor } from "react";

import axios from "axios";

import { Notyf } from "notyf";
import 'notyf/notyf.min.css';

import ReactQuill, { Value } from 'react-quill';
import 'react-quill/dist/quill.snow.css';
import Swal, { SweetAlertOptions } from "sweetalert2";

import { deepObjectMerge } from "./helper";

/** Components */
import InputLookup from "./Inputs/Lookup";
import InputVarchar from "./Inputs/Varchar";
import InputTextarea from "./Inputs/Textarea";
import InputInt from "./Inputs/Int";
import InputBoolean from "./Inputs/Boolean";
import InputMapPoint from "./Inputs/MapPoint";
import InputColor from "./Inputs/Color";
import InputImage from "./Inputs/Image";
import InputTags from "./Inputs/Tags";
import InputDateTime from "./Inputs/DateTime";
import InputEnumValues from "./Inputs/EnumValues";

interface Content {
  [key: string]: ContentCard|any;
}

interface ContentCard {
  title: string
}

export interface FormProps {
  uid: string,
  model: string,
  id?: number,
  readonly?: boolean,
  content?: Content,
  layout?: Array<Array<string>>,
  onSaveCallback?: () => void,
  onDeleteCallback?: () => void,
  hideOverlay?: boolean,
  showInModal?: boolean,
  columns?: FormColumns,
  titleForInserting?: string,
  titleForEditing?: string,
  saveButtonText?: string,
  addButtonText?: string
}

interface FormState {
  content?: Content,
  columns?: FormColumns,
  inputs?: FormInputs,
  isEdit: boolean,
  invalidInputs: Object,
  tabs?: any,
  folderUrl?: string,
  addButtonText?: string,
  saveButtonText?: string,
  titleForEditing?: string,
  titleForInserting?: string,
  layout?: string
}

export interface FormColumnParams {
  title: string,
  type: string,
  length?: number,
  required?: boolean,
  description?: string,
  disabled?: boolean,
  model?: string,
  enum_values?: Array<string|number>,
  unit?: string,
  defaultValue?: any
}

export interface FormColumns {
  [key: string]: FormColumnParams;
}

interface FormInputs {
  [key: string]: string|number;
}

export default class Form extends Component<FormProps> {
  state: FormState;

  model: String;
  inputs: FormInputs = {};

  constructor(props: FormProps) {
    super(props);

    this.state = {
      content: props.content,
      layout: this.convertLayoutToString(props.layout),
      isEdit: false,
      invalidInputs: {},
      inputs: {}
    };

    // this.updateLayout();
  }

  /**
   * This function trigger if something change, for Form id of record
   */
  componentDidUpdate(prevProps: any) {
    if (prevProps.id !== this.props.id) {
      this.checkIfIsEdit();
      this.loadData();

      this.setState({
        invalidInputs: {},
        isEdit: this.props.id ? this.props.id > 0 : false
      });
    }
  }

  componentDidMount() {
    this.checkIfIsEdit();
    this.initTabs();

    this.loadParams();
  }

  loadParams() {
    //@ts-ignore
    axios.get(_APP_URL + '/Components/Form/OnLoadParams', {
      params: {
        model: this.props.model,
        columns: this.props.columns
      }
    }).then(({data}: any) => {
      data = deepObjectMerge(data, this.props);
      data.layout = this.convertLayoutToString(data.layout);

      let newState = {
        columns: data.columns,
        folderUrl: data.folderUrl,
        ...data
      };

      this.setState(newState, () => {
        this.loadData();
        // this.updateLayout();
      });
    });
  }

  loadData() {
    //@ts-ignore
    axios.get(_APP_URL + '/Components/Form/OnLoadData', {
      params: {
        model: this.props.model,
        id: this.props.id
      }
    }).then(({data}: any) => {
      this.initInputs(this.state.columns, data.inputs);
    });
  }

  saveRecord() {
    let notyf = new Notyf();

    //@ts-ignore
    axios.post(_APP_URL + '/Components/Form/OnSave', {
      model: this.props.model,
      inputs: this.state.inputs 
    }).then((res: any) => {
      notyf.success(res.data.message);
      if (this.props.onSaveCallback) this.props.onSaveCallback();
    }).catch((res) => {
      notyf.error(res.response.data.message);

      if (res.response.status == 422) {
        this.setState({
          invalidInputs: res.response.data.invalidInputs 
        });
      }
    });
  }

  deleteRecord(id: number) {
    //@ts-ignore
    Swal.fire({
      title: 'Ste si istý?',
      html: 'Ste si istý, že chcete vymazať tento záznam?',
      icon: 'danger',
      showCancelButton: true,
      cancelButtonText: 'Nie',
      confirmButtonText: 'Áno',
      confirmButtonColor: '#dc4c64',
      reverseButtons: false,
    } as SweetAlertOptions).then((result) => {
      if (result.isConfirmed) {
        let notyf = new Notyf();

        //@ts-ignore
        axios.patch(_APP_URL + '/Components/Form/OnDelete', {
          model: this.props.model,
          id: id
        }).then(() => {
            notyf.success("Záznam zmazaný");
            if (this.props.onDeleteCallback) this.props.onDeleteCallback();
          }).catch((res) => {
            notyf.error(res.response.data.message);

            if (res.response.status == 422) {
              this.setState({
                invalidInputs: res.response.data.invalidInputs 
              });
            }
          });
      }
    })
  }

  /**
   * Check if is id = undefined or id is > 0
   */
  checkIfIsEdit() {
    this.state.isEdit = this.props.id && this.props.id > 1 ? true : false;
  }

  /**
    * Input onChange with event parameter 
    */
  inputOnChange(columnName: string, event: React.FormEvent<HTMLInputElement>) {
    let inputValue: string|number = event.currentTarget.value;

    this.inputOnChangeRaw(columnName, inputValue);
  }

  /**
    * Input onChange with raw input value, change inputs (React state)
    */
  inputOnChangeRaw(columnName: string, inputValue: any) {
    let changedInput: any = {};
    changedInput[columnName] = inputValue;

    this.setState({
      inputs: {...this.state.inputs, ...changedInput}
    });
  }

  /**
    * Dynamically initialize inputs (React state) from model columns
    */
  initInputs(columns?: FormColumns, inputsValues?: Array<any>) {
    let inputs: any = {};

    if (!columns) return;

    Object.keys(columns).map((columnName: string) => {
      switch (columns[columnName]['type']) {
        case 'image':
          inputs[columnName] = {
            fileName: inputsValues[columnName] ?? inputsValues[columnName],
            fileData: inputsValues[columnName] != undefined && inputsValues[columnName] != ""
              ? this.state.folderUrl + '/' + inputsValues[columnName]
              : null
          };
          break;
        case 'bool':
        case 'boolean':
          inputs[columnName] = inputsValues[columnName] ?? this.getDefaultValueForInput(columnName, 0);
          break;
        default:
          inputs[columnName] = inputsValues[columnName] ?? this.getDefaultValueForInput(columnName, null);
      }
    });

    this.setState({
      inputs: inputs
    });
  }

  /**
   * Get default value form Model definition
   */
  getDefaultValueForInput(columnName: string, otherValue: any): any {
    if (!this.state.columns) return;
    return this.state.columns[columnName].defaultValue ?? otherValue
  }

  changeTab(changeTabName: string) {
    let tabs: any = {};

    Object.keys(this.state.tabs).map((tabName: string) => {
      tabs[tabName] = {
        active: tabName == changeTabName
      };
    });

    this.setState({
      tabs: tabs
    });
  }

  /*
    * Initialize form tabs is are defined
    */
  initTabs() {
    if (this.state.content?.tabs == undefined) return;

    let tabs: any = {};
    let firstIteration: boolean = true;

    Object.keys(this.state.content?.tabs).map((tabName: string) => {
      tabs[tabName] = {
        active: firstIteration 
      };

      firstIteration = false;
    });

    this.setState({
      tabs: tabs
    });  
  }

  convertLayoutToString(layout?: Array<Array<string>>): string {
    //@ts-ignore
    return layout?.map(row => `"${row.join(' ')}"`).join('\n');
  }

  /**
    * Render tab
    */
  _renderTab(): JSX.Element {
    if (this.state.content?.tabs) {
      let tabs: any = Object.keys(this.state.content.tabs).map((tabName: string) => {
        return this._renderTabContent(tabName, this.state.content?.tabs[tabName]);
      })

      return tabs;
    } else {
      return this._renderTabContent("default", this.state.content);
    } 
  }

  /*
    * Render tab content
    * If tab is not set, use default tabName else use activated one
    */
  _renderTabContent(tabName: string, content: any) {
    if (
      tabName == "default" 
        || (this.state.tabs && this.state.tabs[tabName]['active'])
    ) {
      return (
        <div 
          style={{
            display: 'grid', 
            gridTemplateRows: 'auto', 
            gridTemplateAreas: this.state.layout, 
            gridGap: '15px'
          }}
        >
          {this.state.content != null ? 
            Object.keys(content).map((contentArea: string) => {
              return this._renderContentItem(contentArea, content[contentArea]); 
            })
            : this.state.inputs != null ? (
              Object.keys(this.state.inputs).map((inputName: string) => {
                if (
                  this.state.columns == null 
                    || this.state.columns[inputName] == null
                ) return <strong style={{color: 'red'}}>Not defined params for {inputName}</strong>;

                return this._renderInput(inputName)
              })
            ) : ''}
        </div>
      );
    } else {
      return <></>;
    }
  }

  /**
    * Render content item 
    */
  _renderContentItem(contentItemArea: string, contentItemParams: undefined|string|Object|Array<string>): JSX.Element {
    if (contentItemParams == undefined) return <b style={{color: 'red'}}>Content item params are not defined</b>;

    let contentItemKeys = Object.keys(contentItemParams);
    if (contentItemKeys.length == 0) return <b style={{color: 'red'}}>Bad content item definition</b>;

    let contentItemName = contentItemArea == "inputs" 
      ? contentItemArea : contentItemKeys[0];

    let contentItem: JSX.Element;

    switch (contentItemName) {
      case 'input': 
        contentItem = this._renderInput(contentItemParams['input'] as string);
        break;
      case 'inputs':
        contentItem = (contentItemParams['inputs'] as Array<string>).map((input: string) => {
          return this._renderInput(input)
        });
        break;
      case 'html': 
        contentItem = (<div dangerouslySetInnerHTML={{ __html: contentItemParams['html'] }} />);
        break;
      default: 
        contentItem = window.getComponent(contentItemName, contentItemParams[contentItemName]);
    }

    return (
      <div style={{gridArea: contentItemArea}}>
        {contentItem}
      </div>
    );
  }

  /**
    * Render different input types
    */
  _renderInput(columnName: string): JSX.Element {
    if (this.state.columns == null) return <></>;

    let inputToRender: JSX.Element;

    if (this.state.columns[columnName].enum_values) {
      inputToRender = <InputEnumValues
        parentForm={this}
        columnName={columnName}
      />
    } else {
      switch (this.state.columns[columnName].type) {
        case 'text':
          inputToRender = <InputTextarea 
            parentForm={this}
            columnName={columnName}
          />;
          break;
        case 'float':
        case 'int':
          inputToRender = <InputInt 
            parentForm={this}
            columnName={columnName}
          />;
          break;
        case 'boolean':
          inputToRender = <InputBoolean 
            parentForm={this}
            columnName={columnName}
          />;
          break;
        case 'lookup':
          inputToRender = <InputLookup 
            parentForm={this}
            {...this.state.columns[columnName]}
            columnName={columnName}
          />;
          break;
        case 'MapPoint':
          inputToRender = <InputMapPoint
            parentForm={this}
            columnName={columnName}
          />;
          break;
        case 'color':
          inputToRender = <InputColor
            parentForm={this}
            columnName={columnName}
          />;
          break;
        case 'tags':
          inputToRender = <InputTags
            parentForm={this}
            columnName={columnName}
          />;
          break;
        case 'image':
          inputToRender = <InputImage
            parentForm={this}
            columnName={columnName}
          />;
          break;
        case 'datetime':
        case 'date':
        case 'time':
          inputToRender = <InputDateTime
            parentForm={this}
            columnName={columnName}
            type={this.state.columns[columnName].type}
          />;
          break;
        case 'editor':
          inputToRender = (
            <div className={'h-100 form-control ' + `${this.state.invalidInputs[columnName] ? 'is-invalid' : 'border-0'}`}>
              <ReactQuill 
                theme="snow" 
                value={this.inputs[columnName] as Value} 
                onChange={(value) => this.inputOnChangeRaw(columnName, value)}
                className="w-100" 
              />
            </div>
          );
          break;
        default:
          inputToRender = <InputVarchar
            parentForm={this}
            columnName={columnName}
          />
      }
    }

    return columnName != 'id' ? (
      <div 
        className="input-form mb-3"
        key={columnName}
      >
        <label className="text-dark">
          {this.state.columns[columnName].title}
          {this.state.columns[columnName].required == true ? <b className="text-danger"> *</b> : ""}
        </label>

        <div 
          className="input-group"
          key={columnName}
        >
          {inputToRender}

          {this.state.columns[columnName].unit ? (
            <div className="input-group-append">
              <span className="input-group-text">{this.state.columns[columnName].unit}</span>
            </div>
          ) : ''}
        </div>

        <small className="form-text text-muted">{this.state.columns[columnName].description}</small>
      </div>
    ) : <></>;
  }

  _renderActionButtons(): JSX.Element {
    return (
      <div className="d-flex">
        <button 
          onClick={() => this.saveRecord()}
          className="btn btn-sm btn-primary btn-icon-split"
        >
          {this.state.isEdit
            ? (
              <>
                <span className="icon"><i className="fas fa-save"></i></span>
                <span className="text"> {this.state.saveButtonText ?? "Uložiť záznam"}</span>
              </>
            )
            : (
              <>
                <span className="icon"><i className="fas fa-plus"></i></span>
                <span className="text"> {this.state.addButtonText ?? "Pridať záznam"}</span>
              </>
            )
          }
        </button>

        {this.state.isEdit ? <button 
          onClick={() => this.deleteRecord(this.props.id ?? 0)}
          className="ml-2 btn btn-danger btn-sm btn-icon-split"
        >
          <span className="icon"><i className="fas fa-trash"></i></span>
          <span className="text">  Zmazať</span>
        </button> : ''}
      </div>
    );
  }

  render() {
    return (
      <>
        {this.props.showInModal ? (
          <div className="modal-header">
            <div className="row w-100 p-0 m-0 d-flex align-items-center justify-content-center">
              <div className="col-lg-4">
                {this._renderActionButtons()}
              </div>
              <div className="col-lg-4 text-center">
                <h3
                  id={'adios-modal-title-' + this.props.uid}
                  className="m-0 p-0"
                >
                  {this.state.isEdit ? this.state.titleForEditing : this.state.titleForInserting}
                </h3>
              </div>
              <div className="col-lg-4 d-flex flex-row-reverse">
                <button 
                  className="btn btn-light"
                  type="button" 
                  data-dismiss="modal" 
                  aria-label="Close"
                ><span>&times;</span></button>
              </div>
            </div>
          </div>
        ) : ''}

        <div
          id={"adios-form-" + this.props.uid}
          className="adios react ui form"
        >
            {this.props.showInModal ? (
              <div className="modal-body">
                {this._renderTab()}
              </div>
            ) : (
              <div className="card w-100">
                <div className="card-header">
                  <div className="row">
                    <div className="col-lg-12 m-0 p-0">
                      <h3 className="card-title">
                        {this.state.isEdit ? this.state.titleForEditing : this.state.titleForInserting}
                      </h3>
                    </div>

                    {this._renderActionButtons()}

                    {this.state.tabs != undefined ? (
                      <ul className="nav nav-tabs card-header-tabs mt-3">
                        {Object.keys(this.state.tabs).map((tabName: string) => {
                          return (
                            <li className="nav-item"> 
                              <button 
                                className={this.state.tabs[tabName]['active'] ? 'nav-link active' : 'nav-link'}
                                onClick={() => this.changeTab(tabName)}
                              >{ tabName }</button> 
                            </li>
                          );
                        })}
                      </ul>
                    ) : ''}
                  </div>
                </div>

                <div className="card-body">
                  {this._renderTab()}
                </div>
              </div>
            )}
        </div>
      </>
    );
  }
}
