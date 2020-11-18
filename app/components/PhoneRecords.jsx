import React, { useState, useEffect } from "react"
import Moment from 'react-moment'
import Button from 'react-bootstrap/Button'
import Container from 'react-bootstrap/Container'
import Row from 'react-bootstrap/Row'
import Col from 'react-bootstrap/Col'
import Table from 'react-bootstrap/Table'
import _ from 'underscore'
import { BsArrowLeft, BsCloudDownload } from "react-icons/bs";

export default function Admin(props) {
    const { back, data } = props
    const { campaign, phoneRecords } = data

    return (
        <Container>
            <Row>
                <Col>
                    <h4>Phone Records: {campaign.post_title}</h4>
                </Col>
                <Col style={{textAlign:"right",  verticalAlign: "bottom"}}>
                    <Button variant="link" onClick={() => back()}>
                        <BsArrowLeft /> Back to Campaigns
                    </Button>
                </Col>
            </Row>
        <Table striped bordered hover>
            <thead>
                <tr>
                    <th>Phone Number</th>
                    <th>Date/Time</th>
                    <th style={{textAlign:'center'}}>Status</th>
                    <th style={{textAlign:'center'}}>Recording</th>
                </tr>
            </thead>
            <tbody>
                {
                    _.map(phoneRecords, phone => 
                        <tr key={phone.ID}>
                            <td>{phone.post_title}</td>
                            <td>
                                { phone.meta.modified && <Moment date={phone.meta.modified[0]} format="lll" /> }
                            </td>
                            <td style={{textAlign:'center'}}>
                                {phone.meta.status[0]}
                                {/* <Form.Check 
                                    type="switch"
                                    inline
                                    defaultChecked={campaign.meta.active !== undefined ? campaign.meta.active[0] : false}
                                /> */}
                            </td>
                            
                            <td style={{textAlign:'center'}}>
                                {phone.meta.RecordingUrl && phone.meta.RecordingUrl[0] && 
                                    <audio style={{height:20}}
                                        controls
                                        src={phone.meta.RecordingUrl}>
                                            Your browser does not support the
                                            <code>audio</code> element.
                                    </audio>
                                }
                            </td>
                        </tr>
                    )
                }
            </tbody>
        </Table>
        </Container>
    )
}