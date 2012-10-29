<?php

class ControllerMultisellerSeller extends ControllerMultisellerBase {
	public function jxSaveSellerInfo() {
		$this->validate(__FUNCTION__);
		$data = $this->request->post;
		$seller = $this->MsLoader->MsSeller->getSellerData($data['seller_id']);
		$json = array();
		
		if (empty($seller)) {
			if (empty($data['sellerinfo_nickname'])) {
				$json['errors']['sellerinfo_nickname'] = 'Username cannot be empty'; 
			} else if (!ctype_alnum($data['sellerinfo_nickname'])) {
				$json['errors']['sellerinfo_nickname'] = 'Username can only contain alphanumeric characters';
			} else if (strlen($data['sellerinfo_nickname']) < 4 || strlen($data['sellerinfo_nickname']) > 50 ) {
				$json['errors']['sellerinfo_nickname'] = 'Username should be between 4 and 50 characters';			
			} else if ($this->MsLoader->MsSeller->nicknameTaken($data['sellerinfo_nickname'])) {
				$json['errors']['sellerinfo_nickname'] = 'This username is already taken';
			}
		}
		
		if (strlen($data['sellerinfo_company']) > 50 ) {
			$json['errors']['sellerinfo_company'] = 'Company name cannot be longer than 50 characters';			
		}
		
		if (empty($json['errors'])) {
			if (!isset($data['sellerinfo_message'])) $data['sellerinfo_message'] = '';
				
			$mails = array();
			if ($data['sellerinfo_action'] != 0) {
				switch ($data['sellerinfo_action']) {
					// enable
					case 1:
						$data['seller_status_id'] = MsSeller::MS_SELLER_STATUS_ACTIVE;
						$request_status = MsRequest::MS_REQUEST_STATUS_APPROVED;
						$mails[] = array(
							'type' => MsMail::SMT_SELLER_ACCOUNT_ENABLED,
							'data' => array(
								'recipients' => $this->MsLoader->MsSeller->getSellerEmail($data['seller_id']),
								'addressee' => $this->MsLoader->MsSeller->getSellerName($data['seller_id']),
								'message' => $data['sellerinfo_message']
							)
						);
						break;
					
					// disable
					case 2:
						$data['seller_status_id'] = MsSeller::MS_SELLER_STATUS_DISABLED;
						$request_status = MsRequest::MS_REQUEST_STATUS_DECLINED;
						$mails[] = array(
							'type' => MsMail::SMT_SELLER_ACCOUNT_DISABLED,
							'data' => array(
								'recipients' => $this->MsLoader->MsSeller->getSellerEmail($data['seller_id']),
								'addressee' => $this->MsLoader->MsSeller->getSellerName($data['seller_id']),
								'message' => $data['sellerinfo_message']
							)
						);
						break;
						
					// approve
					case 3:
						$data['seller_status_id'] = MsSeller::MS_SELLER_STATUS_ACTIVE;
						$request_status = MsRequest::MS_REQUEST_STATUS_APPROVED;						
						$mails[] = array(
							'type' => MsMail::SMT_SELLER_ACCOUNT_APPROVED,
							'data' => array(
								'recipients' => $this->MsLoader->MsSeller->getSellerEmail($data['seller_id']),
								'addressee' => $this->MsLoader->MsSeller->getSellerName($data['seller_id']),
								'message' => $data['sellerinfo_message']
							)
						);
						break;
						
					// decline
					case 4:
						$data['seller_status_id'] = MsSeller::MS_SELLER_STATUS_INACTIVE;
						$request_status = MsRequest::MS_REQUEST_STATUS_DECLINED;
						$mails[] = array(
							'type' => MsMail::SMT_SELLER_ACCOUNT_DECLINED,
							'data' => array(
								'recipients' => $this->MsLoader->MsSeller->getSellerEmail($data['seller_id']),
								'addressee' => $this->MsLoader->MsSeller->getSellerName($data['seller_id']),
								'message' => $data['sellerinfo_message']
							)
						);
						break;
				}
				
				$requests = $this->MsLoader->MsRequestSeller->getSellerRequests(array(
					'seller_id' => $data['seller_id'],
					'request_type' => array(MsRequest::MS_REQUEST_TYPE_SELLER_CREATE, MsRequest::MS_REQUEST_TYPE_SELLER_UPDATE),
					'request_status' => array(MsRequest::MS_REQUEST_STATUS_PENDING)
				));
				
				foreach($requests as $r) {
					$this->MsLoader->MsRequest->processRequest($r['request_id'], array(
						'request_status' => $request_status,
						'processed_by' => $this->user->getId(),
						'message_processed' => $data['sellerinfo_message']
					));
				}
			} else {
				$data['seller_status_id'] = $seller['seller_status_id'];
				$mails[] = array(
					'type' => MsMail::SMT_SELLER_ACCOUNT_MODIFIED,
					'data' => array(
						'recipients' => $this->MsLoader->MsSeller->getSellerEmail($data['seller_id']),
						'addressee' => $this->MsLoader->MsSeller->getSellerName($data['seller_id']),
						'message' => $data['sellerinfo_message']
					)
				);				
			}
			// edit seller
			$this->MsLoader->MsSeller->adminEditSeller($data);
			
			if ($data['sellerinfo_notify']) {
				$this->MsLoader->MsMail->sendMails($mails);
			}
			
			$this->session->data['success'] = 'Seller account data saved.';
		}
		
		$this->response->setOutput(json_encode($json));
	}	
	
	public function index() {
		$this->validate(__FUNCTION__);
		
		/*
		$columns = array(
			'name',
			'nickname',
			'email',
			'total_products',
			'total_sales',
			'total_earnings',	
			'current_balance',
			'seller_status_id',
			'date_created',
		);
		*/
		
		
		$page = isset($this->request->get['page']) ? $this->request->get['page'] : 1;
		
		$orderby = isset($this->request->get['orderby']) && in_array($this->request->get['orderby'], $columns) ? $this->request->get['orderby'] : 'date_created';
		
		$orderway = isset($this->request->get['orderway']) ? $this->request->get['orderway'] : 'DESC';
		
		$sort = array(
			'order_by'  => $orderby,
			'order_way' => $orderway,
			'page' => $page,
			'limit' => $this->config->get('config_admin_limit')
		);

		$results = $this->MsLoader->MsSeller->getSellers($sort);
		$total_sellers = $this->MsLoader->MsSeller->getTotalSellers($sort);

    	foreach ($results as &$result) {
    		$result['date_created'] = date($this->language->get('date_format_short'), strtotime($result['date_created']));
    		$result['total_products'] = $this->MsLoader->MsSeller->getTotalSellerProducts($result['seller_id']);
			//$result['total_earnings'] = $this->currency->format($this->MsLoader->MsSeller->getEarningsForSeller($result['seller_id']), $this->config->get('config_currency'));
			$result['current_balance'] = $this->currency->format($this->MsLoader->MsBalance->getSellerBalance($result['seller_id']), $this->config->get('config_currency'));
			$result['total_sales'] = $this->MsLoader->MsSeller->getSalesForSeller($result['seller_id']);
			$result['status'] = $this->MsLoader->MsSeller->getSellerStatus($result['seller_status_id']);
			$result['action'][] = array(
				'text' => $this->language->get('text_view'),
				'href' => $this->url->link('multiseller/seller/update', 'token=' . $this->session->data['token'] . '&seller_id=' . $result['seller_id'], 'SSL')
			);
			
			$result['customer_link'] = $this->url->link('sale/customer/update', 'token=' . $this->session->data['token'] . '&customer_id=' . $result['seller_id'], 'SSL');
		}
			
		$this->data['sellers'] = $results;
			
		$pagination = new Pagination();
		$pagination->total = $total_sellers;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_admin_limit');
		$pagination->text = $this->language->get('text_pagination');
		$pagination->url = $this->url->link("module/{$this->name}/sellers", 'token=' . $this->session->data['token'] . '&page={page}', 'SSL');
			
		$this->data['pagination'] = $pagination->render();
		
		if (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$this->data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$this->data['success'] = '';
		}		

		/*
		foreach($columns as $column) {
			$this->data["link_sort_$column"] = $this->url->link("module/{$this->name}/sellers", 'token=' . $this->session->data['token'] . "&orderby=$column" . $url, 'SSL');
		}
		*/
		$this->data['token'] = $this->session->data['token'];		
		$this->data['heading'] = $this->language->get('ms_catalog_sellers_heading');
		$this->document->setTitle($this->language->get('ms_catalog_sellers_heading'));
		
		$this->data['breadcrumbs'] = $this->MsLoader->MsHelper->admSetBreadcrumbs(array(
			array(
				'text' => $this->language->get('ms_menu_multiseller'),
				'href' => $this->url->link('multiseller/dashboard', '', 'SSL'),
			),
			array(
				'text' => $this->language->get('ms_catalog_sellers_breadcrumbs'),
				'href' => $this->url->link('multiseller/seller', '', 'SSL'),
			)
		));
		
		list($this->template, $this->children) = $this->MsLoader->MsHelper->admLoadTemplate('seller');
		$this->response->setOutput($this->render());
	}
	
	public function update() {
		$this->validate(__FUNCTION__);
		
		$this->load->model('localisation/country');
    	$this->data['countries'] = $this->model_localisation_country->getCountries();		

		$seller = $this->MsLoader->MsSeller->getSellerData($this->request->get['seller_id']);

		if (!empty($seller)) {
			$this->data['seller'] = $seller;
			$this->data['seller']['status'] = $this->MsLoader->MsSeller->getSellerStatus($seller['seller_status_id']);
			if (!empty($seller['avatar_path'])) {
				$this->data['seller']['avatar']['name'] = $seller['avatar_path'];
				$this->data['seller']['avatar']['thumb'] = $this->MsLoader->MsFile->resizeImage($seller['avatar_path'], $this->config->get('msconf_image_preview_width'), $this->config->get('msconf_image_preview_height'));
				//$this->session->data['multiseller']['files'][] = $seller['avatar_path'];
			}
			
			// seller status action selector
			if (in_array($seller['seller_status_id'], array(
					MsSeller::MS_SELLER_STATUS_INACTIVE,
					MsSeller::MS_SELLER_STATUS_DISABLED,
					MsSeller::MS_SELLER_STATUS_TOBEACTIVATED
			))) {			
				$this->data['actions'][] = array(
					'text' => $this->language->get('ms_enable'),
					'value' => 1
				);
			}
			
			if (in_array($seller['seller_status_id'], array(
					MsSeller::MS_SELLER_STATUS_ACTIVE,
					MsSeller::MS_SELLER_STATUS_TOBEACTIVATED
			))) {			
				$this->data['actions'][] = array(
					'text' => $this->language->get('ms_disable'),
					'value' => 2
				);
			}
			
			if ($seller['seller_status_id'] == MsSeller::MS_SELLER_STATUS_TOBEAPPROVED) {
				$this->data['actions'][] = array(
					'text' => $this->language->get('ms_approve'),
					'value' => 3
				);
				$this->data['actions'][] = array(
					'text' => $this->language->get('ms_decline'),
					'value' => 4
				);
			}
			//
		}

		$this->data['currency_code'] = $this->config->get('config_currency');
		$this->data['token'] = $this->session->data['token'];		
		$this->data['heading'] = $this->language->get('ms_catalog_sellerinfo_heading');
		$this->document->setTitle($this->language->get('ms_catalog_sellerinfo_heading'));
		
		$this->data['breadcrumbs'] = $this->MsLoader->MsHelper->admSetBreadcrumbs(array(
			array(
				'text' => $this->language->get('ms_menu_multiseller'),
				'href' => $this->url->link('multiseller/dashboard', '', 'SSL'),
			),
			array(
				'text' => $this->language->get('ms_catalog_sellers_breadcrumbs'),
				'href' => $this->url->link('multiseller/seller', '', 'SSL'),
			),			
			array(
				'text' => $seller['nickname'],
				'href' => $this->url->link('multiseller/seller/update', '&seller_id=' . $seller['seller_id'], 'SSL'),
			)
		));		
		
		list($this->template, $this->children) = $this->MsLoader->MsHelper->admLoadTemplate('seller-form');
		$this->response->setOutput($this->render());
	}
}
?>
