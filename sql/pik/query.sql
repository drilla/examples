(select 'a_acceptance_document' as parameter, status, business_units, count(status) as param_sum, customer_id, contract_id, date from
  (select
     calculate_deadline_status(sch_obligation_execution_doc_events.deadline, sch_obligation_execution_doc_events.decision_date, sch_obligation_execution_doc_events.status = 'EXECUTED', sch_obligation_execution_doc_events.status = 'EXECUTING' OR sch_obligation_execution_doc_events.status = 'SIGNING') as status,
     sch_obligation_execution_documents.business_unit_ids_json as business_units,
     customer.id as customer_id,
     cnt_contracts.id as contract_id,
     coalesce(sch_obligation_execution_doc_events.decision_date, sch_obligation_execution_doc_events.deadline)::date as date
   from
     sch_obligation_execution_doc_events
     INNER JOIN sch_obligation_execution_documents ON sch_obligation_execution_doc_events.obligation_exec_doc_id = sch_obligation_execution_documents.id
     INNER JOIN sch_account_doc_requirements ON sch_obligation_execution_documents.accounting_documents_requirement_id = sch_account_doc_requirements.id
     INNER JOIN sch_account_doc_requirement_events ON sch_obligation_execution_doc_events.acc_doc_req_event_id = sch_account_doc_requirement_events.id
     INNER JOIN cnt_contract_sides ON sch_account_doc_requirement_events.responsible_side_id = cnt_contract_sides.id
     INNER JOIN sch_electronic_schedules ON cnt_contract_sides.electronic_schedule_id = sch_electronic_schedules.id
     INNER JOIN cnt_contracts ON sch_electronic_schedules.contract_id = cnt_contracts.id
     inner join ref_customer_versions rcv on cnt_contracts.customer_version_id = rcv.id
     inner join ref_customers customer on rcv.customer_id = customer.id
   WHERE  sch_obligation_execution_doc_events.deadline is not null
          AND sch_account_doc_requirements.document_is_acceptable
          AND sch_account_doc_requirement_events.sign_order_number = 1
          AND cnt_contracts.sign_date >= '2018-01-01'::timestamp
          AND sch_electronic_schedules.is_executed_outside = 'FALSE'
          AND sch_obligation_execution_documents.status in ('AGREEMENT', 'APPROVED', 'DISAPPROVED')
          and cnt_contracts.contract_status in ('EXECUTED', 'ON_EXECUTION', 'TERMINATED')) as calculated_statuses
group by parameter, status, date, customer_id, business_units, contract_id)
union all
(select 'b_acceptance' as parameter, status, business_units, count(status) as param_sum, customer_id, contract_id, date from
  (select
     calculate_deadline_status(sch_obligation_execution_doc_events.deadline, sch_obligation_execution_doc_events.decision_date, sch_obligation_execution_doc_events.status = 'EXECUTED', sch_obligation_execution_doc_events.status = 'EXECUTING' OR sch_obligation_execution_doc_events.status = 'SIGNING') as status,
     sch_obligation_execution_documents.business_unit_ids_json as business_units,
     customer.id as customer_id,
     cnt_contracts.id as contract_id,
     coalesce(sch_obligation_execution_doc_events.decision_date, sch_obligation_execution_doc_events.deadline)::date as date
   from
     sch_obligation_execution_doc_events
     INNER JOIN sch_obligation_execution_documents ON sch_obligation_execution_doc_events.obligation_exec_doc_id = sch_obligation_execution_documents.id
     INNER JOIN sch_account_doc_requirements ON sch_obligation_execution_documents.accounting_documents_requirement_id = sch_account_doc_requirements.id
     INNER JOIN sch_account_doc_requirement_events ON sch_obligation_execution_doc_events.acc_doc_req_event_id = sch_account_doc_requirement_events.id
     INNER JOIN cnt_contract_sides ON sch_account_doc_requirement_events.responsible_side_id = cnt_contract_sides.id
     INNER JOIN sch_electronic_schedules ON cnt_contract_sides.electronic_schedule_id = sch_electronic_schedules.id
     INNER JOIN cnt_contracts ON sch_electronic_schedules.contract_id = cnt_contracts.id
     inner join ref_customer_versions rcv on cnt_contracts.customer_version_id = rcv.id
     inner join ref_customers customer on rcv.customer_id = customer.id
   WHERE  sch_obligation_execution_doc_events.deadline is not null
          AND sch_account_doc_requirements.document_is_acceptable
          AND sch_account_doc_requirement_events.sign_order_number = 2
          AND cnt_contracts.sign_date >= '2018-01-01'::timestamp
          AND sch_electronic_schedules.is_executed_outside = 'FALSE'
          AND sch_obligation_execution_documents.status in ('AGREEMENT', 'APPROVED', 'DISAPPROVED')
          and cnt_contracts.contract_status in ('EXECUTED', 'ON_EXECUTION', 'TERMINATED')) as calculated_statuses
group by parameter, status, date, customer_id, business_units, contract_id)
union all
(select 'c_payment' as parameter, status, business_units, count(status) as param_sum, customer_id, contract_id, date from
  (select
     (case
      when execution.end_date is null and plan_start_date::date < current_date
        then '_001_EXECUTION_OVERDUE'
      when execution.end_date is null and plan_start_date::date > current_date and plan_start_date::date < current_date+3
        then '_020_EXECUTION_CLOSE_TO_DEADLINE'
      when execution.end_date is null and plan_start_date::date > current_date+2
        then '_030_EXECUTION_NORMAL'
      when execution.end_date::date > plan_start_date::date
        then '_040_EXECUTED_NOT_IN_TIME'
      when execution.end_date::date <= plan_start_date::date
        then '_050_EXECUTED_IN_TIME'
      else null
      end) as status,
     execution.business_unit_ids_json as business_units,
     customer.id as customer_id,
     contract.id as contract_id,
     coalesce(execution.end_date, plan_start_date)::date as date
   FROM sch_obligation_executions AS execution
     INNER JOIN sch_electr_schedule_obligations AS obligation ON execution.electronic_schedule_obligation_id = obligation.id
     INNER JOIN sch_electronic_schedules AS schedule ON obligation.electronic_schedule_id = schedule.id
     INNER JOIN cnt_contracts AS contract ON schedule.contract_id = contract.id
     inner join ref_customer_versions rcv on contract.customer_version_id = rcv.id
     inner join ref_customers customer on rcv.customer_id = customer.id
   WHERE (schedule.schedule_status = 'POSTED')
         AND (contract.sign_date >= '2018-01-01'::timestamp)
         AND (execution.plan_start_date IS NOT NULL)
         AND (execution.obligation_execution_status not in ('EXECUTION_ABORTED', 'EXECUTION_CANCELED'))
         AND (schedule.is_executed_outside = 'FALSE')
         AND (obligation.type in ('PAYMENT_UNDER_CONTRACT', 'PENALTY_PAYMENT'))
         AND NOT obligation.is_pre_payment
         and contract.contract_status in ('EXECUTED', 'ON_EXECUTION', 'TERMINATED')) as calculated_statuses where status is not null
group by parameter, status, date, customer_id, business_units, contract_id)
