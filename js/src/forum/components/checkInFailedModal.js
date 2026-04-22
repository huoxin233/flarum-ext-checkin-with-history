import Modal from 'flarum/common/components/Modal';

export default class checkInResultModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);
  }

  className() {
    return 'checkInResultModal Modal--small';
  }

  title() {
    return (<div className="checkInResultModal failedTitleText">{app.translator.trans('ziven-checkin.forum.check-in-failed')}</div>);
  }

  content() {
    //
    return (
      <div className="Modal-body">
        <div className="checkInResultModal successText">{app.translator.trans('ziven-checkin.forum.try-again-later')}</div>
      </div>
    );
  }
}
