import React, { Component } from 'react';
import ReactDOM from 'react-dom';
import * as uuid from 'uuid';

import './Css/Modal.css';

export interface ModalProps {
  //onClose?: () => void;
  uid: string,
  type?: string,
  children?: any;
  isActive?: boolean;
  title?: string;
  hideHeader?: boolean;
  isOpen?: boolean;
}

interface ModalState {
  uid: string,
  type: string,
  isActive: boolean;
  title?: string;
}

export default class Modal extends Component<ModalProps> {
  private modalRoot: HTMLDivElement;
  state: ModalState;

  constructor(props: ModalProps) {
    super(props);

    this.state = {
      uid: this.props.uid ?? uuid.v4(),
      type: this.props.type ?? "right",
      isActive: true,
      title: props.title
    };

    this.modalRoot = document.createElement('div');
    document.body.appendChild(this.modalRoot);
  };

  componentWillUnmount() {
    document.body.removeChild(this.modalRoot);
  }

  componentDidMount() {
    if (this.props.isOpen === true) {
      window.adiosModalToggle(this.state.uid);
    }
  }

  render() {
    return ReactDOM.createPortal(
      <div
        id={'adios-modal-' + this.state.uid} 
        className={"modal " + this.state.type + " fade"}
        role="dialog"
      >
        <div className="modal-dialog" role="document">
          <div className="modal-content">
            {this.props.hideHeader ? (
              <div 
                id={'adios-modal-body-' + this.state.uid}
              >
                {this.props.children}
              </div>
            ) : (
              <>
                <div className="modal-header text-left">
                  <button 
                    className="btn btn-light"
                    type="button" 
                    data-dismiss="modal" 
                    aria-label="Close"
                  ><span>&times;</span></button>

                  {this.state.title ? (
                    <h4 
                      className="modal-title text-dark"
                      id={'adios-modal-title-' + this.state.uid}
                    >{this.state.title}</h4>
                  ) : ''}
                </div>

                <div 
                  id={'adios-modal-body-' + this.state.uid}
                  className="modal-body"
                >
                  {this.props.children}
                </div>
              </>
            )}
          </div>
        </div>
      </div>,
      this.modalRoot
    );
  } 
}
