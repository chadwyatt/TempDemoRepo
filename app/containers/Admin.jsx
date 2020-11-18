import React, { useState, useEffect } from "react"
import Button from 'react-bootstrap/Button'
import Form from 'react-bootstrap/Form'
import Container from 'react-bootstrap/Container'
import Row from 'react-bootstrap/Row'
import Col from 'react-bootstrap/Col'
import Table from 'react-bootstrap/Table'
import Modal from 'react-bootstrap/Modal'
import Alert from 'react-bootstrap/Alert'
import _ from 'underscore'
import fetchWP from '../utils/fetchWP'
import { ToastContainer, toast } from 'react-toastify'
import { BsPencil, BsTrash, BsArrowLeft, BsBarChart } from "react-icons/bs";
import 'bootstrap/dist/css/bootstrap.min.css'
import 'react-toastify/dist/ReactToastify.css'
import 'react-bootstrap-typeahead/css/Typeahead.css'
import { Typeahead } from 'react-bootstrap-typeahead'
import PhoneRecords from '../components/PhoneRecords.jsx'

export default function Admin(props){
	const [generalSettings, setGeneralSettings] = useState({})
	const [isLoadingUser, setIsLoadingUser] = useState(true)
	const [currentUserID, setCurrentUserID] = useState(null)
	const [exampleSetting, setExampleSetting] = useState("")
	const [campaignSettings, setCampaignSettings] = useState(null)
	const [campaignPhoneRecords, setCampaignPhoneRecords] = useState(null)
	const [phoneNumbers, setPhoneNumbers] = useState(null)
	const [campaigns, setCampaigns] = useState(null)
	const [showConfirmDelete, setShowConfirmDelete] = useState(false)
	const [currentCampaignID, setCurrentCampaignID] = useState(null)
	const [twilioConnected, setTwilioConnected] = useState(false)
	const [processingOn, setProcessingOn] = useState(false)
	const [processingUrl, setProcessingUrl] = useState(null)
	const [showImportModal, setShowImportModal] = useState(false)
	const [importListID, setImportListID] = useState(0)
	
	useEffect(() => {
		getGeneralSettings()
		getCurrentUserID()
	}, [])

	useEffect(()=>{
		if(!processingOn)
			return

		req.get('ppvmd/run_campaigns')
    	const interval = setInterval(() => {
			if(processingOn){
				req.get('ppvmd/run_campaigns')
			}
		},60000)
		return()=>clearInterval(interval)

	},[processingOn])
	
	useEffect(() => {
		if(currentUserID !== null && currentUserID !== false && currentUserID > 0)
			getCampaigns()
	}, [currentUserID])

	const req = new fetchWP({
		restURL: props.wpObject.api_url,
		restNonce: props.wpObject.api_nonce,
	})

	const getGeneralSettings = () => {
		req.get('twilio/generalSettings')
		.then(
			json => {
				setGeneralSettings(json.value)
				setProcessingUrl(json.value.run_campaigns_url)
			},
			err => console.error(err)
		)
	}

	const getCurrentUserID = () => {
		req.get( 'twilio/user' )
		.then(
			json => {
				setCurrentUserID(json.value.data.ID)
				setIsLoadingUser(false)
			},
			err => {
				console.error(err)
				setIsLoadingUser(false)
			}
		)
	}

	const toObject = (arr, key) => arr.reduce((a, b) => ({ ...a, [b[key]]: b }), {});

	const getCampaigns = () => {
		req.get( 'twilio/campaigns' )
		.then(
			json => {
				const c = toObject(json.value, 'ID')
				setCampaigns(c)
			},
			err => console.error(err)
		)
	}

	const getPhoneNumbers = (ID) => {
		req.get( `twilio/phone_numbers/${ID}` )
		.then(
			json => {
				const numbers = {}
				_.map(json.value.incoming_phone_numbers.incoming_phone_numbers, number => {
					numbers[number.sid] = number
				})
				_.map(json.value.outgoing_caller_ids.outgoing_caller_ids, number => {
					numbers[number.sid] = number
				})
				setPhoneNumbers(numbers)
			},
			err => console.error( 'error', err )
		);
  	}

	const createCampaign = () => {
		const url = `twilio/campaign`
		if(campaignSettings.sid === null || campaignSettings.sid === undefined || campaignSettings.token === null || campaignSettings.token === undefined){
			toast("Twilio SID and Token can not be blank.", {type:"error"})
			return
		}

		req.post( url, { campaignSettings })
		.then(
			(json) => {
				toast("Campaign Created", {type:"success"})
				setCampaignSettings(json.value)
				getPhoneNumbers(json.value.ID)
				getCampaigns()	
			},
			(err) => toast("Error", {type:"error"})
		)
	}

	const updateCampaign = () => {
		const url = campaignSettings.ID !== undefined ? `twilio/campaign/${campaignSettings.ID}` : `twilio/campaign`

		// if(campaignSettings.sid === undefined && campaignSettings.meta.sid !== undefined)
		// 	campaignSettings.sid = campaignSettings.meta.sid[0]
		
		// if(campaignSettings.token === undefined && campaignSettings.meta.token !== undefined)
		// 	campaignSettings.token = campaignSettings.meta.token[0]
		

		req.post( url, { campaignSettings })
		.then(
			(json) => {
				toast("Settings Saved", {type:"success"})
				// setCampaignSettings(json.value)
				// getPhoneNumbers()
				getCampaigns()	
			},
			(err) => toast("Error", {type:"error"})
		)
	}
	  
	const getCampaign = (ID) => {
		setCurrentCampaignID(ID)
		req.get( `twilio/campaign/${ID}`)
		.then(
			json => {
				setCampaignSettings(json.value)
				getPhoneNumbers(ID)
			},
			err => console.error( 'error', err )
		);
	}

	const confirmDeleteCampaign = (ID) => {
		setCurrentCampaignID(ID)
		setShowConfirmDelete(true)
	}

	const deleteCampaign = () => {
		setCampaigns(_.without(campaigns, _.findWhere(campaigns, {ID: currentCampaignID})))
		setShowConfirmDelete(false)
		req.delete( 'twilio/campaign', {ID: currentCampaignID} )
		.then(
			(json) => {
				toast("Campaign Deleted", {type:"success"})
			},
			(err) => toast("Error", {type:"error"})
		)
	}

	const getCampaignPhoneRecords = (ID) => {
		req.get( `twilio/campaignPhoneRecords/${ID}`)
		.then(
			json => {
				setCampaignPhoneRecords({campaign: _.find(campaigns, campaign => campaign.ID === ID), phoneRecords: json.value})
			},
			err => console.error( 'error', err )
		);
	}

	const newCampaign = () => {
		setCampaignSettings({})
		setPhoneNumbers(null)
	}

	

	const updateCampaignStatus = (ID, checked) => {
		if(campaigns[ID].sid === undefined || campaigns[ID].sid === null || campaigns[ID].token === undefined || campaigns[ID].token === null){
			toast(<small>You must update your twilio settings for this campaign before making it active.</small>, {type:"error"})
			return
		}

		if(campaigns[ID].audioFileUrl === undefined || campaigns[ID].audioFileUrl === null || _.isEmpty(campaigns[ID].audioFileUrl)){
			toast(<small>You must add an Audio File URL to your campaign before making it active.</small>, {type:"error"})
			return
		}


		const update = campaigns
		update[ID].active = checked ? "1" : "0"
		update[ID].update_status_only = "1"
		setCampaigns({...campaigns, [ID]:{...update[ID]}})

		req.post( `twilio/campaign/${ID}`, { campaignSettings: update[ID] })
		.then(
			(json) => {
				toast("Settings Saved", {type:"success"})
				getCampaigns()	
			},
			(err) => toast("Error", {type:"error"})
		)
	}

	const Confirm = () => {
		return (
			<>
			<Modal show={showConfirmDelete} onHide={() => setShowConfirmDelete(false)}>
				<Modal.Header closeButton>
				<Modal.Title>Confirm Delete</Modal.Title>
				</Modal.Header>
				<Modal.Body>Are you sure you want to delete this campaign?</Modal.Body>
				<Modal.Footer>
				<Button variant="secondary" onClick={() => setShowConfirmDelete(false)}>
					Close
				</Button>
				<Button variant="danger" onClick={() => deleteCampaign()}>
					Delete Campaign
				</Button>
				</Modal.Footer>
			</Modal>
			</>
		)
	}

	const importList = () => {
		if(importListID === 0)
			return

		req.get( `twilio/import_list/${importListID}`)
		.then(
			json => {
				setCampaignSettings({ ...campaignSettings, phoneNumbers: json.value })
				setShowImportModal(false)
			},
			err => console.error(err)
		)
	}

	const Polling = () => {
		return (
			<Alert variant="primary" className="mt-4">
				<p>You'll need to add this URL to a cron job at <a href="https://console.cron-job.org/" target="_blank">cron-job.org</a> in order to run campaigns while not logged into your website.</p>
				<p><code>{processingUrl}</code></p>
				<p>
					Optionally, you can turn on processing from this page, keep in mind you'll need to keep this page open in a tab in order for voicemails to process.
				</p>
				<div>
					<Form.Check 
						key={`switch-polling`}
						id={`switch-polling`}
						type="switch"
						inline
						label={`Processing is ${processingOn ? "ON" : "OFF"}`}
						checked={processingOn}
						onChange={e => setProcessingOn(e.target.checked)}
					/>
				</div>
			</Alert>
		)
	}

	if(isLoadingUser){
		return <div>Loading...</div>
	}

	if(currentUserID === null || currentUserID === undefined){
		return (
			<div><a href={generalSettings.login_url || ""}>Login</a> required</div>
		)
	}

	if(campaignPhoneRecords !== null){
		return (
			<>
			<PhoneRecords back={() => setCampaignPhoneRecords(null)} data={campaignPhoneRecords} />

			</>
		)
	}

	return (
    	<Container className="mt-4">
			{campaignSettings === null && 
				<>
				<Row>
					<Col md={8}><h2>Voicemail Drop Campaigns</h2></Col>	
					<Col md={4}style={{textAlign:"right"}}><Button variant="primary" onClick={() => newCampaign()}>New Campaign</Button></Col>
				</Row>
				<Table striped bordered hover>
					<thead>
						<tr>
							<th>Campaign Name</th>
							<th style={{textAlign:'center'}}>Active</th>
							<th style={{textAlign:'center'}}>Actions</th>
						</tr>
					</thead>
					<tbody>
						{campaigns !== null &&
							_.map(campaigns, campaign => 
									<tr key={campaign.ID}>
										<td>{campaign.post_title}</td>
										<td style={{textAlign:'center'}}>
										<Form.Check 
											key={`switch-${campaign.ID}`}
											id={`switch-${campaign.ID}`}
											type="switch"
											inline
											checked={campaign.active === '1'}
											onChange={e => updateCampaignStatus(campaign.ID, e.target.checked)}
											disabled={campaign.phone_numbers_queued === "1"}
										/>
										</td>
										<td style={{textAlign:'center'}}>
											<BsBarChart style={{cursor:"pointer"}} onClick={() => getCampaignPhoneRecords(campaign.ID)} />
											<BsPencil style={{cursor:"pointer"}} className="ml-2" onClick={() => getCampaign(campaign.ID)}/> 
											<BsTrash style={{cursor:"pointer"}} className="ml-2" onClick={() => confirmDeleteCampaign(campaign.ID)} />
										</td>
									</tr>
								)
						}
					</tbody>
				</Table>
				</>
			}
			{campaignSettings !== null && 
				<Form>
					<Row>
						<Col>
							<h2>Campaign Settings</h2>
						</Col>
						<Col style={{textAlign:"right",  verticalAlign: "bottom"}}>
							<Button variant="link" style={{verticalAlign: "bottom"}} onClick={() => setCampaignSettings(null)}>
								<BsArrowLeft /> Back to Campaigns
							</Button>
						</Col>
					</Row>
					<Row>
						<Col>
							<Form.Label>Campaign Name</Form.Label>
							<Form.Control className="ml-1 mb-2 mr-sm-2" value={campaignSettings.post_title} onChange={e => setCampaignSettings({...campaignSettings, post_title: e.target.value })}></Form.Control>
						</Col>
					</Row>
					<Row>
						<Col md={6}>
							<Form.Label>Twilio SID:</Form.Label>
							<Form.Control className="ml-1 mb-2 mr-sm-2" value={campaignSettings.sid} onChange={e => setCampaignSettings({...campaignSettings, sid: e.target.value })}></Form.Control>
						</Col>
						<Col md={6}>
							<Form.Label>Twilio Token:</Form.Label>
							<Form.Control type="password" className="ml-1 mb-2 mr-sm-2" value={campaignSettings.token} onChange={e => setCampaignSettings({...campaignSettings, token: e.target.value })}></Form.Control>
						</Col>
					</Row>
					{campaignSettings === {} || phoneNumbers === null && 
						<Row>
							<Col>
								<Button variant="primary" className="ml-1 mt-4" onClick={() => createCampaign()}>Next</Button>
							</Col>
						</Row>
					}
					{phoneNumbers !== null &&
						<>
						<Row>
							<Col>
								<Form.Group>
									<Form.Label>From Phone Number</Form.Label>
									{phoneNumbers !== null &&
										<Typeahead
											id="caller_id"
											onChange={(selected) => {
												setCampaignSettings({...campaignSettings, selectedPhoneNumber: selected })
											}}
											options={
												_.map(phoneNumbers, number => {
													return {id: number.phone_number, key: number.sid, label: number.friendly_name }
												})
											}
											selected={campaignSettings.selectedPhoneNumber || []}
										/>
									}
								</Form.Group>
							</Col>
						</Row>
						<Row>
							<Col>
								<Form.Group>
									<Form.Label>
										Phone Numbers <small>(One per line)</small>
									</Form.Label>
									<Form.Control as="textarea" rows={5} value={campaignSettings.phoneNumbers || ""} onChange={e => setCampaignSettings({...campaignSettings, phoneNumbers: e.target.value })} />
									{campaignSettings.lists !== undefined && campaignSettings.lists.length > 0 && <Button variant="link" size="sm"
										onClick={() => setShowImportModal(true)}
									>+ Import from Lead Finder</Button>}
								</Form.Group>
							</Col>
						</Row>
						<Row>
							<Col>
								<Form.Group>
									<Form.Label>Audio File URL</Form.Label>
									<Form.Control className="ml-1 mb-2 mr-sm-2" value={campaignSettings.audioFileUrl || ""} onChange={e => setCampaignSettings({...campaignSettings, audioFileUrl: e.target.value })}></Form.Control>
								</Form.Group>
							</Col>
						</Row>
						<Row>
							<Col>
								<Form.Group>
									<Form.Check 
										type="switch"
										id="mobile_only"
										label="Mobile Only"
										inline
										checked={campaignSettings.mobileOnly || false}
										onChange={e => {
											setCampaignSettings({...campaignSettings, mobileOnly: e.target.checked })
										}}
									/>
								</Form.Group>
							</Col>
						</Row>
						<Row>
							<Col>
								<Button className="mb-2 mr-4" onClick={() => updateCampaign()} disabled={campaignSettings.phone_numbers_queued === "1"}>
									Save
								</Button>
								{/* <Form.Check 
									type="switch"
									id="campaign_active"
									label="Active"
									inline
									checked={campaignSettings.active || false}
									onChange={e => {
										setCampaignSettings({...campaignSettings, active: e.target.checked })
									}}
								/> */}
							</Col>
						</Row>
						</>
					}
				</Form>
			}
			<ToastContainer position="top-center" />
			<Confirm />
			<Polling />
			{/* <ImportModal /> */}
			<Modal show={showImportModal} onHide={() => setShowImportModal(false)}>
				<Modal.Header closeButton>
				<Modal.Title>Import from Lead Finder</Modal.Title>
				</Modal.Header>
				<Modal.Body>
					<Container>
						<Form.Group>
							<Form.Label>List <small>(Any existing phone numbers will be replaced)</small></Form.Label>
							{campaignSettings !== null && campaignSettings.lists !== undefined && campaignSettings.lists.length > 0 &&
								<Typeahead
									id="scraper_list"
									onChange={(selected) => {
										setImportListID(selected[0].id)
									}}
									options={
										_.map(campaignSettings.lists, list => {
											return {id: list.ID, key: list.ID, label: list.post_title }
										})
									}
								/>
							}
						</Form.Group>
					</Container>
				</Modal.Body>
				<Modal.Footer>
				<Button variant="secondary" onClick={() => setShowImportModal(false)}>
					Close
				</Button>
				<Button variant="primary" onClick={() => importList()}>
					Import
				</Button>
				</Modal.Footer>
			</Modal>
			
			{/* <ul>
				<li>Add field for phone numbers</li>
				<li>Register new post type for vm drop campaigns with child records for individual drop records</li>
				<li>Register new post type for vm drops to record status, etc</li>
				<li>Create a campaign</li>
				<li>Import into individual records with a custom post-type</li>
				<li>Show dashboard summary of campaign status</li>
				<li>Display table of vm drop records to drill down into campaign details</li>
				<li>Need a cron or something - wp_cron and/or an interval from the page itself</li>
				<li>Add typeahead field for phone numbers</li>
				<li>Save twilio accounts</li>
			</ul> */}
        </Container>
  	);
}