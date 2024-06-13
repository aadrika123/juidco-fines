<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Rig Machine License</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #fff;
      display: flex;
      justify-content: center;
      height: 100vh;
    }

    .certificate_background {
      position: relative;
      padding: 10px;
      border: 2px solid #000000;
      height: fit-content;
      margin: 50px;
    }

    .watermark {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      opacity: 0.1;
      z-index: 0;
    }

    .certificate {
      position: relative;
      z-index: 1;
      height: fit-content;
      border: 2px dashed #800000;
      padding: 20px;
      margin: 25px;
      display: flex;
      flex-direction: column;
      width: 800px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
      font-size: 0.9em;
      /* Adjust this to make the text smaller */
    }

    .title {
      text-align: center;
      color: #800000;
      font-weight: bold;
      margin-bottom: 5px;
      font-size: 1.2em;
      /* Adjust this to make the title smaller */
    }

    .subtitle {
      text-align: center;
      color: #800000;
      margin-bottom: 5px;
      font-size: 1em;
      /* Adjust this to make the subtitle smaller */
    }

    .note {
      text-align: center;
      color: #666;
      font-style: italic;
      margin-bottom: 10px;
      font-size: 0.8em;
      /* Adjust this to make the note smaller */
    }

    .details p,
    .declaration p,
    .conditions li,
    .footer p {
      margin: 5px 0;
      color: #800000;
      font-size: 0.9em;
      /* Adjust this to make the text smaller */
    }

    .details {
      margin-top: 25px;
      color: #800000;
      font-weight: bold;
    }

    .rightdetails {
      float: right;
      display: flex;
      flex-direction: column;
    }

    .declaration_2 {
      display: flex;
      color: #800000;
    }

    .details span,
    .highlight {
      font-weight: 100;
      margin-left: 25px;
    }

    .declaration {
      margin-top: 25px;
      margin-bottom: 25px;
    }

    .conditions {
      margin-left: 5px;
      list-style-type: decimal;
    }

    .footer_div {
      display: flex;
      flex-direction: row;
    }

    .qr {
      display: flex;
    }

    .footer {
      display: flex;
      flex: 1;
      flex-direction: column;
      align-items: flex-start;
      justify-content: flex-start;
      margin-top: 20px;
      text-align: center;
      font-size: 0.8em;
      /* Adjust this to make the footer text smaller */
      margin-left: 25px;
    }

    .footer a {
      color: #0000ee;
      text-decoration: none;
    }

    .footer a:hover {
      text-decoration: underline;
    }
  </style>
</head>

<body>
  <div class="certificate_background">
    <img src="./logo.png" class="watermark" width="500" height="500" alt="Logo Watermark" />
    <div class="certificate">
      <h2 class="title">Ranchi Municipal Corporation</h2>
      <h3 class="subtitle">Rig Machine License</h3>
      <p class="note">
        (This Certificate relates to Under Jharkhand Municipal Act of 2011)
      </p>
      <div class="details">
        <p>
          Municipal Rig Machine Registration : <span>{{$data["RegistrationNo"]}}</span>
        </p>
        <p>Date Of Approval : <span>{{$data["approveDate"]}}</span></p>
        <p>
          Validity Of Municipal Rig License :
          <span>{{$data["approveDate"]}} To {{$data["approveEndDate"]}}</span>
        </p>
        <p>Licence Owner Name : <span>{{$data["AplicantName"]}}</span></p>
        <p>Vehicle Registration No. : <span>{{$data["vehicleNo"]}}</span></p>
        <div class="rightdetails">
          <p>Application No : <span>{{$data["applicationNo"]}}</span></p>
          <p>Date Of Application : <span>{{$data["applyDate"]}}</span></p>
          <p>Mobile No : <span>{{$data["mobileNo"]}}</span></p>
        </div>
      </div>
      <div class="declaration_2">
        <p>
          This is to declare that
          <span class="highlight">"RIG MACHINE"</span> having registration
          licence number <span class="highlight">{{$data["RegistrationNo"]}}</span> for
          4 Inches drilling has been allocated to
          <span class="highlight">{{$data["AplicantName"]}}</span> as mentioned in
          Jharkhand Municipal Act 2011 in the regime of Ranchi Municipal
          Corporation. The validity of this licence is for TWO YEARS along
          with following terms and conditions:-
        </p>
      </div>
      <ul class="conditions">
        <li>Business will run according to license issued.</li>
        <li>
          Prior information to local body regarding winding up/closer of
          business is necessary.
        </li>
        <li>
          Application for renewal of license is necessary one month before
          expiry of license.
        </li>
        <li>
          In the case of delay, penalty will be levied according to section
          209 of Jharkhand Municipal Act 2011.
        </li>
      </ul>
      <div class="footer_div">
        <div class="qr">
          <img src="./qr.png" height="150px" width="150px" alt="QR Code" />
        </div>
        <div class="footer">
          <p>
            For More Details Please Visit:
            <a href="http://jharkhandegovernance.com">jharkhandegovernance.com</a>
          </p>
          <p>
            OR Call us at <a href="tel:18008904115">18008904115</a> or
            <a href="tel:0651-3500700">0651-3500700</a>
          </p>
          <p class="note">
            Note: This is a computer generated License and it does not require
            a signature.
          </p>
        </div>
      </div>
    </div>
  </div>
</body>

</html>